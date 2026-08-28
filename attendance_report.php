<?php
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: login.php");
    exit;
}

require_once 'db_config.php';

$selectedGroup = $_GET['group_id'] ?? '';
$selectedTeacher = $_GET['teacher_id'] ?? '';
$selectedDate = $_GET['session_date'] ?? '';

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

$statusMap = [
    'present' => 'נוכח',
    'late' => 'מאחר',
    'absent' => 'חסר'
];

$statusClassMap = [
    'present' => 'status-present',
    'late' => 'status-late',
    'absent' => 'status-absent'
];

$errorMessage = '';
$groups = [];
$teachers = [];
$attendanceRows = [];
$totalRows = 0;
$presentCount = 0;
$lateCount = 0;
$absentCount = 0;

try {

    /*
        שליפת קבוצות לדוח:
        משתמשים ב-DISTINCT וגם בשדה teacher_id כדי שכל קבוצה תופיע פעם אחת בלבד,
        וכדי שנוכל לסנן בצד הלקוח את הקבוצות לפי המורה שנבחר.
    */
    $groupsStmt = $pdo->prepare("
        SELECT DISTINCT
            tg.group_id,
            tg.teacher_id,
            tg.grade_level,
            s.subject_name,
            CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
        FROM tutoring_groups tg
        JOIN subjects s ON tg.subject_id = s.subject_id
        JOIN teachers t ON tg.teacher_id = t.teacher_id
        WHERE tg.status = 'approved'
        ORDER BY tg.group_id DESC
    ");
    $groupsStmt->execute();
    $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
        שכבת הגנה נוספת:
        אם בגלל מבנה נתונים או JOIN עתידי אותה קבוצה תחזור יותר מפעם אחת,
        נשאיר רק מופע אחד לפי group_id.
    */
    $uniqueGroups = [];

    foreach ($groups as $group) {
        $uniqueGroups[(int)$group['group_id']] = $group;
    }

    $groups = array_values($uniqueGroups);

    $teachersStmt = $pdo->prepare("
        SELECT teacher_id, first_name, last_name
        FROM teachers
        ORDER BY first_name, last_name
    ");
    $teachersStmt->execute();
    $teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

    $where = [];
    $params = [];

    if ($selectedGroup !== '') {
        $where[] = "tg.group_id = :group_id";
        $params[':group_id'] = (int)$selectedGroup;
    }

    if ($selectedTeacher !== '') {
        $where[] = "t.teacher_id = :teacher_id";
        $params[':teacher_id'] = (int)$selectedTeacher;
    }

    if ($selectedDate !== '') {
        $where[] = "ts.session_date = :session_date";
        $params[':session_date'] = $selectedDate;
    }

    $whereSql = '';
    if (count($where) > 0) {
        $whereSql = "WHERE " . implode(" AND ", $where);
    }

    $reportQuery = "
        SELECT 
            ts.id AS session_id,
            ts.session_date,
            ts.topic,
            tg.group_id,
            tg.grade_level,
            s.subject_name,
            CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
            st.student_id,
            st.first_name AS student_first_name,
            st.last_name AS student_last_name,
            st.class_name,
            tsa.status AS attendance_status
        FROM tutoring_sessions ts
        JOIN tutoring_groups tg ON ts.group_id = tg.group_id
        JOIN subjects s ON tg.subject_id = s.subject_id
        JOIN teachers t ON tg.teacher_id = t.teacher_id
        JOIN tutoring_session_attendance tsa ON ts.id = tsa.session_id
        JOIN students st ON tsa.student_id = st.student_id
        $whereSql
        ORDER BY ts.session_date DESC, ts.id DESC, tg.group_id DESC, st.last_name, st.first_name
    ";

    $stmt = $pdo->prepare($reportQuery);
    $stmt->execute($params);
    $attendanceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRows = count($attendanceRows);

    foreach ($attendanceRows as $row) {
        if ($row['attendance_status'] === 'present') {
            $presentCount++;
        } elseif ($row['attendance_status'] === 'late') {
            $lateCount++;
        } elseif ($row['attendance_status'] === 'absent') {
            $absentCount++;
        }
    }

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - דוחות נוכחות</title>

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

        /* סרגל ניווט עליון קבוע ואחיד לשאר דפי המערכת */
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

        .top-actions {
            display: flex;
            gap: 8px;
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
            content: '📊';
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

        /* הגדרת פריסת הגריד המקובעת שמצילה את הפרופורציות */
        .dashboard-layout {
            display: grid !important;
            grid-template-columns: 250px 1fr 250px !important;
            gap: 20px !important;
            max-width: 1200px !important;
            margin: 0 auto !important;
            align-items: flex-start !important;
            width: 100% !important;
        }

        .app-container {
            width: 100% !important;
            min-width: 0 !important;
        }

        .side-panel {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(80, 60, 40, 0.03);
            position: sticky;
            top: 180px;
            width: 100% !important;
        }

        .side-panel h3 {
            font-size: 16px;
            color: #4a5153;
            margin-bottom: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            padding-bottom: 6px;
        }

        .side-panel p {
            font-size: 13px !important;
            color: #718096 !important;
            line-height: 1.6 !important;
            white-space: normal !important;
        }

        .stat-badge {
            background: #ffffff;
            border-radius: 14px;
            padding: 14px;
            text-align: center;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            border: 1px solid #edf2f7;
        }

        .stat-badge h4 {
            font-size: 28px;
            color: #8fa3db;
            font-weight: 700;
        }

        .stat-badge span {
            font-size: 13px;
            color: #718096;
        }

        /* פתק ממו צהוב אחיד */
        .card {
            background: #fff9e6 !important;
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important;
            padding: 26px 22px !important;
            border: 1px solid #f3ebd1 !important;
            width: 100% !important;
            box-shadow: 0 8px 20px rgba(80, 60, 40, 0.06) !important;
            margin-bottom: 25px;
        }

        .card h2 {
            font-size: 18px;
            font-weight: 700;
            color: #333a42;
            margin-bottom: 15px;
            text-align: center;
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        label {
            font-weight: 500;
            color: #5a6578;
            font-size: 13px;
        }

        select, input[type="date"] {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            background: #ffffff;
            color: #2d3748;
            outline: none;
            transition: all 0.2s ease;
        }

        select:focus, input[type="date"]:focus {
            border-color: #8fa3db;
            box-shadow: 0 0 0 3px rgba(143, 163, 219, 0.15);
        }

        .btn-submit {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            background: #8fa3db;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(143, 163, 219, 0.35);
            transition: all 0.2s ease;
            text-align: center;
            text-decoration: none;
        }

        .btn-submit:hover {
            background: #7b8fc7;
            box-shadow: 0 6px 14px rgba(143, 163, 219, 0.5);
            transform: translateY(-1px);
        }

        .btn-reset {
            display: block;
            width: 100%;
            padding: 10px;
            background: transparent;
            color: #718096;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 14px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-reset:hover {
            background: rgba(0,0,0,0.03);
            color: #2d3748;
        }

        /* מעטפת לבנה נקייה לטבלה ללא גלילות קשיחות */
        .table-responsive {
            width: 100% !important;
            background: white !important;
            border-radius: 16px !important;
            border: 1px solid #edf2f7 !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02) !important;
            margin-top: 15px !important;
            padding: 5px !important;
        }

        /* הפיכת הטבלה למהודקת (Compact) כדי שתיכנס בול במסך */
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: auto !important;
        }

        th, td {
            padding: 8px 6px !important; /* צמצום המרווחים כדי לחסוך מקום לרוחב */
            text-align: center !important;
            font-size: 12px !important;   /* גודל גופן מהודק למניעת חריגות */
            border-bottom: 1px solid #edf2f7 !important;
            white-space: nowrap !important; /* שומר על תאריכים ושמות בשורה אחת */
        }

        th {
            background: #8fa3db !important;
            color: white !important;
            font-weight: 500 !important;
        }

        .status-pill {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
        }

        .status-present { background: #e8f7ee; color: #0b7a35; }
        .status-late { background: #fff4e5; color: #a15c00; }
        .status-absent { background: #fff5f5; color: #c53030; }

        .no-results {
            text-align: center;
            padding: 30px;
            color: #718096;
            background: white;
            border-radius: 16px;
            border: 1px dashed #cbd5e1;
            font-size: 14px;
        }

        .error-banner {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        @media (max-width: 1050px) {
            .dashboard-layout {
                grid-template-columns: 1fr !important;
                max-width: 600px;
            }
            .side-panel {
                position: static;
                text-align: center;
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
        <h1>דוחות נוכחות</h1>
        <p>צפייה בנוכחות שמורים הזינו לקבוצות התגבור במערכת.</p>
    </div>
</div>

<div class="dashboard-layout">

    <div class="side-panel">
        <h3>סינון מהיר</h3>
        <p>ניתן לסנן לפי מורה, קבוצה או תאריך מפגש.</p>
        <a class="btn gray" href="attendance_report.php">איפוס סינון</a>
    </div>

    <div class="app-container">

        <div class="card">
            <h2>סינון דוח</h2>
            <p class="note">בחר נתונים לסינון הדוח. ניתן להשאיר את כל השדות ריקים כדי לראות את כל הדיווחים.</p>

            <form method="GET">
                <div class="filters-grid">

                    <div>
                        <label>בחר מורה</label>
                        <select name="teacher_id" id="teacherSelect" onchange="filterGroupsByTeacher()">
                            <option value="">כל המורים</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= htmlspecialchars($teacher['teacher_id']) ?>" <?= ((string)$selectedTeacher === (string)$teacher['teacher_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>בחר קבוצת תגבור</label>
                        <select name="group_id" id="groupSelect">
                            <option value="">כל הקבוצות</option>
                            <?php foreach ($groups as $group): ?>
                                <?php
                                    $groupSubject = $subjectMap[$group['subject_name']] ?? $group['subject_name'];
                                    $groupTeacherId = (string)$group['teacher_id'];
                                    $shouldHideGroup = ($selectedTeacher !== '' && (string)$selectedTeacher !== $groupTeacherId);
                                ?>
                                <option
                                    value="<?= htmlspecialchars($group['group_id']) ?>"
                                    data-teacher-id="<?= htmlspecialchars($groupTeacherId) ?>"
                                    <?= $shouldHideGroup ? 'style="display:none;" disabled' : '' ?>
                                    <?= ((string)$selectedGroup === (string)$group['group_id']) ? 'selected' : '' ?>
                                >
                                    קבוצה <?= htmlspecialchars($group['group_id']) ?> | <?= htmlspecialchars($groupSubject) ?> | שכבה <?= htmlspecialchars($group['grade_level']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>תאריך מפגש</label>
                        <input type="date" name="session_date" value="<?= htmlspecialchars($selectedDate) ?>">
                    </div>

                </div>

                <button class="btn" type="submit">הצג דוח</button>
            </form>

            <?php if ($errorMessage !== ''): ?>
                <div class="error-box">
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>תוצאות דוח נוכחות</h2>
            <p class="note">מוצגות רשומות הנוכחות שהוזנו על ידי המורים.</p>

            <?php if ($errorMessage !== ''): ?>

                <div class="empty">
                    קיימת שגיאה בטעינת הדוח. פרטי השגיאה מופיעים למעלה.
                </div>

            <?php elseif (count($attendanceRows) === 0): ?>

                <div class="empty">
                    לא נמצאו רשומות נוכחות עבור הסינון הנוכחי.
                </div>

            <?php else: ?>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>תאריך</th>
                                <th>מורה</th>
                                <th>קבוצה</th>
                                <th>מקצוע</th>
                                <th>שכבה</th>
                                <th>נושא מפגש</th>
                                <th>תלמיד</th>
                                <th>כיתה</th>
                                <th>סטטוס</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($attendanceRows as $row): ?>
                                <?php
                                    $subjectDisplay = $subjectMap[$row['subject_name']] ?? $row['subject_name'];
                                    $statusKey = $row['attendance_status'];
                                    $statusDisplay = $statusMap[$statusKey] ?? $statusKey;
                                    $statusClass = $statusClassMap[$statusKey] ?? '';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['session_date']) ?></td>
                                    <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                                    <td>קבוצה <?= htmlspecialchars($row['group_id']) ?></td>
                                    <td><?= htmlspecialchars($subjectDisplay) ?></td>
                                    <td><?= htmlspecialchars($row['grade_level']) ?></td>
                                    <td><?= htmlspecialchars($row['topic']) ?></td>
                                    <td><?= htmlspecialchars($row['student_first_name'] . ' ' . $row['student_last_name']) ?></td>
                                    <td><?= htmlspecialchars($row['class_name']) ?></td>
                                    <td>
                                        <span class="status-pill <?= htmlspecialchars($statusClass) ?>">
                                            <?= htmlspecialchars($statusDisplay) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>

    </div>

    <div class="side-panel">
        <h3>סיכום נוכחות</h3>

        <div class="stat-badge">
            <h4><?= $totalRows ?></h4>
            <span>רשומות בדוח</span>
        </div>

        <div class="stat-badge">
            <h4><?= $presentCount ?></h4>
            <span>נוכחים</span>
        </div>

        <div class="stat-badge">
            <h4><?= $lateCount ?></h4>
            <span>מאחרים</span>
        </div>

        <div class="stat-badge">
            <h4><?= $absentCount ?></h4>
            <span>חסרים</span>
        </div>
    </div>

</div>

<script>
function filterGroupsByTeacher() {
    const teacherSelect = document.getElementById('teacherSelect');
    const groupSelect = document.getElementById('groupSelect');

    if (!teacherSelect || !groupSelect) {
        return;
    }

    const selectedTeacherId = teacherSelect.value;
    let selectedGroupStillVisible = false;

    Array.from(groupSelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = '';
            option.disabled = false;
            return;
        }

        const optionTeacherId = option.getAttribute('data-teacher-id') || '';
        const shouldShow = selectedTeacherId === '' || optionTeacherId === selectedTeacherId;

        option.style.display = shouldShow ? '' : 'none';
        option.disabled = !shouldShow;

        if (option.selected && shouldShow) {
            selectedGroupStillVisible = true;
        }
    });

    if (groupSelect.value !== '' && !selectedGroupStillVisible) {
        groupSelect.value = '';
    }
}

document.addEventListener('DOMContentLoaded', filterGroupsByTeacher);
</script>


</body>
</html>
