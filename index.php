<?php
session_start();

// הגנה על הדף - רק הנהלה / רכז יכולים להיכנס למסך הראשי
if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: login.php");
    exit;
}

require_once 'db_config.php';

// חישוב כמות קבוצות התגבור הפעילות
$countQuery = "
    SELECT COUNT(*) AS total_groups
    FROM tutoring_groups
    WHERE status = 'approved'
";

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute();
$countRow = $countStmt->fetch(PDO::FETCH_ASSOC);

$totalGroupsCount = (int)($countRow['total_groups'] ?? 0);

$outboxCount = (int) $pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - מסך הנהלה</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Rubik', sans-serif !important;
        }

        body {
            direction: rtl;
            background: #fcdbd1; 
            min-height: 100vh;
            color: #2d3748;
            padding: 0 20px 40px 20px;
            display: block !important;
            overflow-y: auto;
        }
        
        .header-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            position: -webkit-sticky;
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(252, 219, 209, 0.92); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding-top: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.02);
        }

        .topbar {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            width: 100% !important;
            margin-bottom: 15px !important;
        }

        .logo {
            font-size: 18px;
            font-weight: 700;
            color: #4a5153;
        }

        .logout {
            background: rgba(255, 255, 255, 0.8);
            color: #718096;
            padding: 6px 14px;
            border-radius: 10px;
            text-decoration: none;
            border: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .logout:hover {
            background: #fff5f5;
            color: #c53030;
            border-color: #fed7d7;
        }

        .hero {
            text-align: center !important;
            margin-bottom: 25px !important;
            width: 100% !important;
        }

        .hero h1 {
            font-size: 34px;
            font-weight: 700;
            color: #4a5153;
            line-height: 1.2;
            position: relative;
            display: inline-block;
        }

        .hero h1::after {
            content: '🎓';
            position: absolute;
            top: -22px;
            right: 8px;
            font-size: 22px;
            transform: rotate(15deg);
        }

        .hero p {
            font-size: 14px;
            color: #718096;
            margin-top: 8px;
            opacity: 0.85;
            line-height: 1.5;
        }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 280px 1fr 280px; 
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            align-items: flex-start;
        }

        .app-container {
            width: 100%;
            max-width: 440px; 
            margin: 0 auto;
        }

        .side-panel {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(80, 60, 40, 0.03);
            position: -webkit-sticky;
            position: sticky;
            top: 180px; 
        }

        .side-panel h3 {
            font-size: 16px;
            color: #4a5153;
            margin-bottom: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            padding-bottom: 6px;
        }

        .side-panel p {
            font-size: 14px;
            color: #718096;
            line-height: 1.6;
        }

        .stat-link {
            display: block;
            text-decoration: none;
            color: inherit;
            margin-top: 10px;
        }

        .stat-badge {
            background: #ffffff;
            border-radius: 14px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            border: 1px solid #edf2f7;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .stat-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(143, 163, 219, 0.25);
            border-color: #8fa3db;
            background: #f8fbff;
        }

        .stat-badge h4 {
            font-size: 32px;
            color: #8fa3db; 
            font-weight: 700;
        }

        .stat-badge span {
            font-size: 13px;
            color: #718096;
            display: block;
            margin-top: 3px;
        }

        .stat-badge small {
            display: block;
            margin-top: 8px;
            color: #8fa3db;
            font-size: 12px;
            font-weight: 500;
        }

        .grid {
            display: flex !important;
            flex-direction: column !important;
            gap: 25px !important; 
            width: 100% !important;
        }

        .card {
            background: #fff9e6 !important;
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important; 
            padding: 28px 22px !important;
            position: relative !important;
            border: 1px solid #f3ebd1 !important;
            width: 100% !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.01), 0 8px 20px rgba(80, 60, 40, 0.06) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(0,0,0,0.02), 0 12px 25px rgba(80, 60, 40, 0.09) !important;
        }

        .card h2 {
            font-size: 18px;
            font-weight: 700;
            color: #333a42;
            margin-bottom: 8px;
            text-align: center;
        }

        .note {
            color: #718096;
            line-height: 1.5;
            font-size: 13px;
            text-align: center;
            margin-bottom: 18px;
        }

        .btn {
            display: block !important;
            width: 100% !important;
            padding: 12px !important;
            margin-top: 15px !important;
            background: #8fa3db !important; 
            color: white !important;
            border: none !important;
            border-radius: 12px !important;
            text-align: center !important;
            text-decoration: none !important;
            cursor: pointer !important;
            font-weight: 500 !important;
            font-size: 15px !important;
            box-shadow: 0 4px 10px rgba(143, 163, 219, 0.35) !important; 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn:hover {
            background: #7b8fc7 !important;
            box-shadow: 0 6px 14px rgba(143, 163, 219, 0.5) !important;
            transform: translateY(-1px) !important;
        }

        .btn.gray {
            background: #718096 !important;
            box-shadow: 0 4px 10px rgba(113, 128, 150, 0.25) !important;
        }

        .btn.gray:hover {
            background: #5f6b7a !important;
            box-shadow: 0 6px 14px rgba(113, 128, 150, 0.35) !important;
        }

        @media (max-width: 1050px) {
            .dashboard-layout {
                grid-template-columns: 1fr; 
                max-width: 440px;
            }
            .side-panel {
                text-align: center;
                position: static;
            }
            .header-container {
                position: static;
                background: transparent;
                backdrop-filter: none;
            }
        }

        @media (max-width: 650px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
                padding: 0;
            }
            .side-panel {
                display: none !important; 
            }
            body {
                padding: 15px 10px !important;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>

<body class="gradeup-ui gradeup-dashboard">

    <div class="header-container">
        <div class="topbar">
            <div class="logo">GradeUp</div>
            <a class="logout" href="logout.php">יציאה</a>
        </div>

        <div class="hero">
            <h1>מערכת ניהול תגבורים</h1>
            <p>מסך הנהלה מרכזי לניהול משתמשים, שיבוץ קבוצות, צפייה בדוחות ומעקב אחר פעילות המורים והתלמידים.</p>
        </div>
    </div>

    <div class="dashboard-layout">

        <div class="side-panel">
            <h3>פרופיל מחובר</h3>

            <p>שלום, <strong><?= htmlspecialchars($_SESSION['user']) ?></strong></p>

            <p style="font-size: 12px; margin-top: 4px; color: #a0aec0;">
                סוג חשבון:
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'coordinator'): ?>
                    הנהלה / רכז מערכת
                <?php else: ?>
                    משתמש מערכת
                <?php endif; ?>
            </p>

            <a class="btn" href="profile_settings.php">הפרופיל שלי</a>
        </div>

        <div class="app-container">
            <div class="grid">
                
                <div class="card">
                    <h2>שיבוץ חכם לקבוצות תגבור</h2>
                    <p class="note">
                        בחירת שכבה ומקצוע, איתור תלמידים מתאימים ויצירת קבוצות תגבור אוטומטיות.
                    </p>
                    <a class="btn" href="smart_matching.php">עבור לשיבוץ חכם</a>
                </div>

                <div class="card">
                    <h2>ניהול משתמשים</h2>
                    <p class="note">
                        יצירת משתמשים חדשים למערכת ואיפוס סיסמאות עבור הנהלה, מורים ותלמידים.
                    </p>
                    <a class="btn" href="manage_users.php">עבור לניהול משתמשים</a>
                </div>

                <div class="card">
                    <h2>דוחות נוכחות</h2>
                    <p class="note">
                        צפייה בנוכחות שמורים הזינו לכל קבוצת תגבור, כולל תאריך מפגש, נושא שיעור וסטטוס תלמידים.
                    </p>
                    <a class="btn" href="attendance_report.php">צפייה בדוחות נוכחות</a>
                </div>

                <div class="card">
                    <h2>מעקב הגשות וציונים</h2>
                    <p class="note">
                        צפייה בהגשות התלמידים, סטטוס ביצוע המשימות והציונים שהתקבלו במערכת.
                    </p>
                    <a class="btn gray" href="assignment_results.php">צפייה ומעקב הגשות תלמידים</a>
                </div>

                <div class="card">
                    <h2>הודעות מערכת</h2>
                    <p class="note">
                        צפייה בהודעות שנוצרו בעקבות שיבוצים, משימות ודיווחי נוכחות. במצב הדגמה ההודעות נשמרות בבטחה במערכת.
                    </p>
                    <a class="btn" href="email_outbox.php">צפייה בתיבת ההודעות (<?= $outboxCount ?>)</a>
                </div>

            </div>
        </div>

        <div class="side-panel">
            <h3>נתונים במערכת</h3>

            <a class="stat-link" href="active_groups.php">
                <div class="stat-badge">
                    <h4><?= htmlspecialchars($totalGroupsCount) ?></h4>
                    <span>קבוצות תגבור פעילות</span>
                    <small>לחץ לצפייה בכל הקבוצות</small>
                </div>
            </a>
        </div>

    </div>

</body>
</html>
