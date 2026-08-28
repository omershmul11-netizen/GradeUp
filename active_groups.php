<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: login.php");
    exit;
}

require_once 'db_config.php';

function subjectToHebrewDisplay($subjectName) {
    $map = [
        'Math' => 'מתמטיקה',
        'Mathematics' => 'מתמטיקה',
        'English' => 'אנגלית',
        'Hebrew' => 'עברית',
        'Computer Science' => 'מדעי המחשב',
        'ComputerScience' => 'מדעי המחשב',
        'CS' => 'מדעי המחשב',
        'Physics' => 'פיזיקה'
    ];

    return $map[$subjectName] ?? $subjectName;
}

function dayToHebrewDisplay($day) {
    $map = [
        'Sunday' => 'ראשון',
        'Monday' => 'שני',
        'Tuesday' => 'שלישי',
        'Wednesday' => 'רביעי',
        'Thursday' => 'חמישי',
        'Friday' => 'שישי',
        'Saturday' => 'שבת'
    ];

    return $map[$day] ?? $day;
}

$groupsQuery = "
    SELECT 
        tg.group_id,
        tg.subject_id,
        s.subject_name,
        tg.grade_level,
        tg.teacher_id,
        CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
        t.email AS teacher_email,
        tg.day_of_week,
        tg.start_time,
        tg.end_time,
        tg.status,
        COUNT(tgs.student_id) AS student_count
    FROM tutoring_groups tg
    JOIN subjects s ON tg.subject_id = s.subject_id
    JOIN teachers t ON tg.teacher_id = t.teacher_id
    LEFT JOIN tutoring_group_students tgs ON tg.group_id = tgs.group_id
    WHERE tg.status = 'approved'
    GROUP BY 
        tg.group_id,
        tg.subject_id,
        s.subject_name,
        tg.grade_level,
        tg.teacher_id,
        t.first_name,
        t.last_name,
        t.email,
        tg.day_of_week,
        tg.start_time,
        tg.end_time,
        tg.status
    ORDER BY tg.group_id DESC
";

$groupsStmt = $pdo->prepare($groupsQuery);
$groupsStmt->execute();
$groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

$studentsByGroup = [];

if (count($groups) > 0) {
    $groupIds = array_map(function($group) {
        return (int)$group['group_id'];
    }, $groups);

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));

    $studentsQuery = "
        SELECT 
            tgs.group_id,
            st.student_id,
            st.first_name,
            st.last_name,
            st.class_name,
            st.email
        FROM tutoring_group_students tgs
        JOIN students st ON tgs.student_id = st.student_id
        WHERE tgs.group_id IN ($placeholders)
        ORDER BY tgs.group_id DESC, st.last_name ASC, st.first_name ASC
    ";

    $studentsStmt = $pdo->prepare($studentsQuery);
    $studentsStmt->execute($groupIds);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $student) {
        $groupId = (int)$student['group_id'];

        if (!isset($studentsByGroup[$groupId])) {
            $studentsByGroup[$groupId] = [];
        }

        $studentsByGroup[$groupId][] = $student;
    }
}

$totalGroupsCount = count($groups);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - קבוצות תגבור פעילות</title>

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

        .top-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .top-link {
            background: rgba(255, 255, 255, 0.8);
            color: #718096;
            padding: 8px 16px;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .top-link:hover {
            background: #fff5f5;
            color: #c53030;
            border-color: #fed7d7;
            transform: translateY(-1px);
        }

        .hero {
            text-align: center;
            margin-bottom: 10px;
        }

        .hero h1 {
            font-size: 34px;
            font-weight: 700;
            color: #4a5153;
            line-height: 1.2;
        }

        .hero p {
            font-size: 14px;
            color: #718096;
            margin-top: 8px;
            opacity: 0.85;
            line-height: 1.5;
        }

        .app-container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .summary-card {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(80, 60, 40, 0.03);
            text-align: center;
        }

        .summary-card h2 {
            font-size: 34px;
            color: #8fa3db;
            margin-bottom: 4px;
        }

        .summary-card p {
            color: #718096;
            font-size: 14px;
        }

        .group-card {
            background: #fff9e6 !important;
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important;
            padding: 26px 22px !important;
            border: 1px solid #f3ebd1 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.01), 0 8px 20px rgba(80, 60, 40, 0.06) !important;
        }

        .group-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            padding-bottom: 14px;
            margin-bottom: 16px;
        }

        .group-title h2 {
            font-size: 20px;
            color: #333a42;
            margin-bottom: 6px;
        }

        .group-title p {
            color: #718096;
            font-size: 13px;
            line-height: 1.6;
        }

        .badge {
            background: #ffffff;
            color: #8fa3db;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 12px 16px;
            min-width: 120px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }

        .badge strong {
            display: block;
            font-size: 24px;
            color: #8fa3db;
        }

        .badge span {
            font-size: 12px;
            color: #718096;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }

        .meta-box {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 12px;
            text-align: center;
        }

        .meta-box span {
            display: block;
            font-size: 12px;
            color: #718096;
            margin-bottom: 5px;
        }

        .meta-box strong {
            font-size: 14px;
            color: #333a42;
        }

        .students-title {
            font-size: 16px;
            color: #333a42;
            margin-bottom: 10px;
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #edf2f7;
        }

        th, td {
            border: 1px solid #edf2f7;
            padding: 10px;
            text-align: center;
            font-size: 13px;
        }

        th {
            background: #8fa3db;
            color: white;
            font-weight: 500;
        }

        .empty {
            background: #fff9e6;
            border: 1px solid #f3ebd1;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            color: #718096;
            box-shadow: 0 8px 20px rgba(80, 60, 40, 0.06);
        }

        @media (max-width: 850px) {
            .meta-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .group-header {
                flex-direction: column;
            }

            .badge {
                width: 100%;
            }
        }

        @media (max-width: 520px) {
            body {
                padding: 15px 10px 30px 10px;
            }

            .header-container {
                position: static;
                background: transparent;
                backdrop-filter: none;
            }

            .hero h1 {
                font-size: 28px;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                gap: 10px;
            }

            .top-actions {
                flex-direction: column;
                width: 100%;
            }

            .top-link {
                width: 100%;
                text-align: center;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>

<body class="gradeup-ui gradeup-workspace">

    <div class="header-container">
        <div class="topbar">
            <div class="logo">GradeUp</div>

            <div class="top-actions">
                <a class="top-link" href="index.php">חזרה למסך הנהלה</a>
                <a class="top-link" href="logout.php">יציאה</a>
            </div>
        </div>

        <div class="hero">
            <h1>קבוצות תגבור פעילות</h1>
            <p>רשימת כל קבוצות התגבור הפעילות במערכת, כולל מורה, מקצוע, שכבה, מועד ורשימת תלמידים.</p>
        </div>
    </div>

    <div class="app-container">

        <div class="summary-card">
            <h2><?= htmlspecialchars($totalGroupsCount) ?></h2>
            <p>קבוצות תגבור פעילות במערכת</p>
        </div>

        <?php if (count($groups) === 0): ?>
            <div class="empty">
                אין כרגע קבוצות תגבור פעילות להצגה.
            </div>
        <?php endif; ?>

        <?php foreach ($groups as $group): ?>
            <?php
                $groupId = (int)$group['group_id'];
                $subjectDisplay = subjectToHebrewDisplay($group['subject_name']);
                $dayDisplay = dayToHebrewDisplay($group['day_of_week']);
                $startTime = substr($group['start_time'], 0, 5);
                $endTime = substr($group['end_time'], 0, 5);
                $students = $studentsByGroup[$groupId] ?? [];
            ?>

            <div class="group-card">
                <div class="group-header">
                    <div class="group-title">
                        <h2>קבוצה <?= htmlspecialchars($groupId) ?></h2>
                        <p>
                            <?= htmlspecialchars($subjectDisplay) ?> |
                            שכבה <?= htmlspecialchars($group['grade_level']) ?> |
                            מורה: <?= htmlspecialchars($group['teacher_name']) ?>
                        </p>
                    </div>

                    <div class="badge">
                        <strong><?= htmlspecialchars($group['student_count']) ?></strong>
                        <span>תלמידים בקבוצה</span>
                    </div>
                </div>

                <div class="meta-grid">
                    <div class="meta-box">
                        <span>מקצוע</span>
                        <strong><?= htmlspecialchars($subjectDisplay) ?></strong>
                    </div>

                    <div class="meta-box">
                        <span>שכבה</span>
                        <strong><?= htmlspecialchars($group['grade_level']) ?></strong>
                    </div>

                    <div class="meta-box">
                        <span>מורה</span>
                        <strong><?= htmlspecialchars($group['teacher_name']) ?></strong>
                    </div>

                    <div class="meta-box">
                        <span>מועד</span>
                        <strong>יום <?= htmlspecialchars($dayDisplay) ?>, <?= htmlspecialchars($startTime) ?>–<?= htmlspecialchars($endTime) ?></strong>
                    </div>
                </div>

                <h3 class="students-title">רשימת תלמידים</h3>

                <?php if (count($students) === 0): ?>
                    <div class="empty" style="padding:18px; border-radius:14px; box-shadow:none;">
                        לא נמצאו תלמידים בקבוצה זו.
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table>
                            <tr>
                                <th>שם תלמיד</th>
                                <th>כיתה</th>
                                <th>מייל</th>
                            </tr>

                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                    <td><?= htmlspecialchars($student['class_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($student['email'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php endforeach; ?>

    </div>

</body>
</html>
