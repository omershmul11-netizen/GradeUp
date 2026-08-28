<?php
session_start();

if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit;
}

require_once 'db_config.php';

$teacherId = (int)$_SESSION['teacher_id'];
$teacherName = $_SESSION['teacher_name'] ?? 'מורה';

$groupsQuery = "
    SELECT 
        tg.group_id,
        s.subject_name,
        tg.grade_level,
        tg.day_of_week,
        tg.start_time,
        tg.end_time,
        tg.status
    FROM tutoring_groups tg
    JOIN subjects s ON tg.subject_id = s.subject_id
    WHERE tg.teacher_id = :teacher_id
      AND tg.status = 'approved'
    ORDER BY tg.group_id DESC
";

$stmt = $pdo->prepare($groupsQuery);
$stmt->execute([
    ':teacher_id' => $teacherId
]);

$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subjectMap = [
    'Math' => 'מתמטיקה',
    'Mathematics' => 'מתמטיקה',
    'Computer Science' => 'מדעי המחשב',
    'ComputerScience' => 'מדעי המחשב',
    'CS' => 'מדעי המחשב',
    'Physics' => 'פיזיקה',
    'Hebrew' => 'עברית',
    'English' => 'אנגלית'
];

$dayMapHebrew = [
    'Sunday' => 'ראשון',
    'Monday' => 'שני',
    'Tuesday' => 'שלישי',
    'Wednesday' => 'רביעי',
    'Thursday' => 'חמישי',
    'Friday' => 'שישי',
    'Saturday' => 'שבת'
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - סביבת מורה</title>

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

        .topbar-actions {
            display: flex;
            gap: 8px;
            align-items: center;
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
            margin-bottom: 10px !important;
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
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .side-panel {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(80, 60, 40, 0.03);
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

        .card {
            background: #fff9e6 !important;
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important;
            padding: 28px 22px !important;
            position: relative !important;
            border: 1px solid #f3ebd1 !important;
            width: 100% !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.01), 0 8px 20px rgba(80, 60, 40, 0.06) !important;
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px) !important;
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
            transition: all 0.2s ease;
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

        .empty {
            text-align: center;
            padding: 20px;
            color: #718096;
            font-size: 14px;
        }

        .group-item {
            background: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px solid #edf2f7;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .group-item:hover {
            border-color: #8fa3db;
            background: #f8fbff;
            transform: translateY(-1px);
        }

        .group-item.active-group {
            border-color: #8fa3db;
            background: #f4f8ff;
            box-shadow: 0 4px 12px rgba(143, 163, 219, 0.18);
        }

        .group-item span {
            font-size: 11px;
            color: #a0aec0;
        }

        .group-students-panel {
            display: none;
            margin-top: 14px;
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 12px;
        }

        .group-students-panel h4 {
            font-size: 14px;
            color: #4a5153;
            margin-bottom: 10px;
            text-align: center;
        }

        .student-mini-item {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 10px;
            padding: 8px;
            margin-bottom: 7px;
            font-size: 12px;
            color: #4a5568;
            text-align: right;
        }

        .student-mini-item strong {
            color: #2d3748;
        }

        .student-mini-item span {
            display: block;
            margin-top: 3px;
            font-size: 11px;
            color: #a0aec0;
        }

        .loading-text {
            text-align: center;
            font-size: 12px;
            color: #718096;
            padding: 10px;
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
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>

<body class="gradeup-ui gradeup-dashboard">

<div class="header-container">
    <div class="topbar">
        <div class="logo">GradeUp - סביבת מורה</div>

        <div class="topbar-actions">
            <a class="logout" href="profile_settings.php">הפרופיל שלי</a>
            <a class="logout" href="teacher_logout.php">יציאה</a>
        </div>
    </div>

    <div class="hero">
        <h1>שלום, <?= htmlspecialchars($teacherName) ?></h1>
        <p>כאן מופיעות רק קבוצות התגבור שאתה אחראי עליהן.</p>
    </div>
</div>

<div class="dashboard-layout">

    <div class="side-panel">
        <h3>פרופיל מורה</h3>
        <p>מורה מחובר: <strong><?= htmlspecialchars($teacherName) ?></strong></p>
        <p style="font-size: 12px; margin-top: 4px; color: #a0aec0;">
            סוג חשבון: סגל הוראה פעיל
        </p>
    </div>

    <div class="app-container">

        <?php if (count($groups) === 0): ?>

            <div class="card">
                <div class="empty">אין לך כרגע קבוצות תגבור משויכות.</div>
            </div>

        <?php else: ?>

            <div class="card">
                <h2>ניהול נוכחות</h2>
                <p class="note">
                    מעבר למסך נפרד שבו ניתן לבחור קבוצת תגבור, להזין תאריך ונושא מפגש, לסמן נוכחות ולשמור למערכת.
                </p>

                <a class="btn" href="teacher_attendance.php">מעבר לניהול נוכחות</a>
            </div>

            <div class="card">
                <h2>משימות ודפי עבודה</h2>
                <p class="note">
                    צור משימות אמריקאיות לקבוצות שאתה אחראי עליהן, ועקוב אחר הגשות התלמידים והציונים.
                </p>

                <a class="btn" href="teacher_assignments.php">יצירת משימות לקבוצות שלי</a>

                <a class="btn gray" href="assignment_results.php">
                    מעקב הגשות וציונים
                </a>
            </div>

        <?php endif; ?>

    </div>

    <div class="side-panel">
        <h3>הקבוצות שלי</h3>

        <?php if (count($groups) === 0): ?>

            <p style="font-size: 13px;">אין קבוצות פעילות.</p>

        <?php else: ?>

            <div style="max-height: 300px; overflow-y: auto;">
                <?php foreach($groups as $group): ?>
                    <?php
                        $subjectDisplay = $subjectMap[$group['subject_name']] ?? $group['subject_name'];
                        $dayHebrew = $dayMapHebrew[$group['day_of_week']] ?? $group['day_of_week'];
                    ?>

                    <div 
                        class="group-item"
                        onclick="showStudentsForGroup(<?= htmlspecialchars($group['group_id']) ?>, this)"
                    >
                        <strong>קבוצה <?= htmlspecialchars($group['group_id']) ?></strong><br>
                        <?= htmlspecialchars($subjectDisplay) ?> | שכבה <?= htmlspecialchars($group['grade_level']) ?><br>
                        <span>
                            יום <?= htmlspecialchars($dayHebrew) ?> ב-<?= substr($group['start_time'], 0, 5) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="group-students-panel" id="groupStudentsPanel">
                <h4 id="groupStudentsTitle">תלמידי הקבוצה</h4>
                <div id="groupStudentsContent"></div>
            </div>

        <?php endif; ?>
    </div>

</div>

<script>
async function showStudentsForGroup(groupId, clickedElement) {
    const panel = document.getElementById('groupStudentsPanel');
    const title = document.getElementById('groupStudentsTitle');
    const content = document.getElementById('groupStudentsContent');

    const isSameGroupOpen =
        clickedElement.classList.contains('active-group') &&
        panel.style.display === 'block' &&
        panel.getAttribute('data-current-group') === String(groupId);

    /*
        אם לוחצים פעם שנייה על אותה קבוצה — סוגרים את רשימת התלמידים.
    */
    if (isSameGroupOpen) {
        clickedElement.classList.remove('active-group');
        panel.style.display = 'none';
        panel.removeAttribute('data-current-group');
        title.innerText = 'תלמידי הקבוצה';
        content.innerHTML = '';
        return;
    }

    /*
        אם לוחצים על קבוצה אחרת — סוגרים סימון קודם ופותחים את הקבוצה החדשה.
    */
    document.querySelectorAll('.group-item').forEach(item => {
        item.classList.remove('active-group');
    });

    clickedElement.classList.add('active-group');

    panel.style.display = 'block';
    panel.setAttribute('data-current-group', String(groupId));
    title.innerText = 'תלמידי קבוצה ' + groupId;
    content.innerHTML = '<div class="loading-text">טוען תלמידים...</div>';

    try {
        const response = await fetch('api/get_group_students.php?group_id=' + encodeURIComponent(groupId));
        const students = await response.json();

        if (!Array.isArray(students)) {
            content.innerHTML = '<div class="student-mini-item">שגיאה בטעינת תלמידים.</div>';
            return;
        }

        if (students.length === 0) {
            content.innerHTML = '<div class="student-mini-item">אין תלמידים בקבוצה זו.</div>';
            return;
        }

        let html = '';

        students.forEach(student => {
            html += `
                <div class="student-mini-item">
                    <strong>${escapeHtml(student.first_name)} ${escapeHtml(student.last_name)}</strong>
                    <span>כיתה: ${escapeHtml(student.class_name || '-')}</span>
                    <span>מייל: ${escapeHtml(student.email || '-')}</span>
                </div>
            `;
        });

        content.innerHTML = html;

    } catch (error) {
        content.innerHTML = '<div class="student-mini-item">שגיאה בתקשורת עם השרת.</div>';
    }
}

function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
</script>

</body>
</html>
