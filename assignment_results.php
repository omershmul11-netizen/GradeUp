<?php
session_start();

/*
    הרשאות:
    מורה יכול להיכנס דרך teacher_id.
    הנהלה/רכז יכול להיכנס דרך user + role coordinator.
    תלמיד לא אמור להיכנס למסך הזה.
*/
$isTeacher = isset($_SESSION['teacher_id']);
$isCoordinator = isset($_SESSION['user']) && isset($_SESSION['role']) && $_SESSION['role'] === 'coordinator';

if (!$isTeacher && !$isCoordinator) {
    header("Location: login.php");
    exit;
}

require_once 'db_config.php';

$teacherId = $isTeacher ? (int)$_SESSION['teacher_id'] : 0;

$selectedGradeLevel = isset($_GET['grade_level']) ? trim($_GET['grade_level']) : '';
$selectedGroupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$selectedAssignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

$permissionMessage = '';

/*
    שליפת קבוצות:
    אם זה מורה — רק הקבוצות שלו.
    אם זו הנהלה — כל הקבוצות.
*/
$groupsQuery = "
    SELECT 
        tg.group_id,
        s.subject_name,
        tg.grade_level,
        CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
        tg.day_of_week,
        tg.start_time
    FROM tutoring_groups tg
    JOIN subjects s ON tg.subject_id = s.subject_id
    JOIN teachers t ON tg.teacher_id = t.teacher_id
";

$groupsParams = [];

if ($isTeacher) {
    $groupsQuery .= " WHERE tg.teacher_id = :teacher_id ";
    $groupsParams[':teacher_id'] = $teacherId;
}

$groupsQuery .= " ORDER BY tg.grade_level ASC, tg.group_id DESC";

$groupsStmt = $pdo->prepare($groupsQuery);
$groupsStmt->execute($groupsParams);
$groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
    בניית רשימת שכבות מתוך הקבוצות שהמשתמש רשאי לראות.
*/
$availableGrades = [];
$groupGradeById = [];

foreach ($groups as $group) {
    $grade = (string)$group['grade_level'];
    $availableGrades[$grade] = $grade;
    $groupGradeById[(int)$group['group_id']] = $grade;
}

sort($availableGrades, SORT_NUMERIC);

/*
    אם נכנסנו עם group_id בלי grade_level,
    נזהה אוטומטית את השכבה של הקבוצה.
*/
if ($selectedGroupId && $selectedGradeLevel === '' && isset($groupGradeById[$selectedGroupId])) {
    $selectedGradeLevel = $groupGradeById[$selectedGroupId];
}

/*
    אם נבחרה קבוצה שלא מתאימה לשכבה שנבחרה — מאפסים.
*/
if (
    $selectedGroupId &&
    $selectedGradeLevel !== '' &&
    isset($groupGradeById[$selectedGroupId]) &&
    (string)$groupGradeById[$selectedGroupId] !== (string)$selectedGradeLevel
) {
    $selectedGroupId = 0;
    $selectedAssignmentId = 0;
    $permissionMessage = 'הקבוצה שנבחרה אינה שייכת לשכבה שנבחרה.';
}

/*
    בדיקת הרשאה לקבוצה שנבחרה:
    אם מורה ניסה להגיע לקבוצה שאינה שלו דרך URL — מאפסים בחירה.
*/
$allowedGroupIds = [];

foreach ($groups as $group) {
    $allowedGroupIds[] = (int)$group['group_id'];
}

if ($selectedGroupId && !in_array($selectedGroupId, $allowedGroupIds, true)) {
    $selectedGroupId = 0;
    $selectedAssignmentId = 0;
    $permissionMessage = 'אין לך הרשאה לצפות בקבוצה זו.';
}

// שליפת רשימת המשימות בהתאם לקבוצה שנבחרה
$assignments = [];

if ($selectedGroupId) {
    $assignmentsQuery = "
        SELECT 
            a.assignment_id,
            a.title,
            a.due_date,
            a.created_at
        FROM assignments a
        JOIN tutoring_groups tg ON a.group_id = tg.group_id
        WHERE a.group_id = :group_id
    ";

    $assignmentsParams = [
        ':group_id' => $selectedGroupId
    ];

    if ($isTeacher) {
        $assignmentsQuery .= " AND tg.teacher_id = :teacher_id ";
        $assignmentsParams[':teacher_id'] = $teacherId;
    }

    $assignmentsQuery .= " ORDER BY a.assignment_id DESC";

    $assignmentsStmt = $pdo->prepare($assignmentsQuery);
    $assignmentsStmt->execute($assignmentsParams);
    $assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
    בדיקת הרשאה למשימה שנבחרה:
    אם assignment_id לא שייכת לקבוצה המותרת — מאפסים.
*/
$allowedAssignmentIds = [];

foreach ($assignments as $assignment) {
    $allowedAssignmentIds[] = (int)$assignment['assignment_id'];
}

if ($selectedAssignmentId && !in_array($selectedAssignmentId, $allowedAssignmentIds, true)) {
    $selectedAssignmentId = 0;

    if ($permissionMessage === '') {
        $permissionMessage = 'אין לך הרשאה לצפות במשימה זו או שהמשימה אינה שייכת לקבוצה שנבחרה.';
    }
}

// שליפת רשימת התלמידים והגשות ה-AI שלהם עבור המשימה שנבחרה
$studentsResults = [];

if ($selectedGroupId && $selectedAssignmentId) {
    $studentsQuery = "
        SELECT 
            s.student_id,
            s.first_name,
            s.last_name,
            s.class_name,
            s.email,
            sub.submission_id,
            sub.ai_score,
            sub.ai_feedback,
            sub.status,
            sub.submitted_at,
            sub.updated_at
        FROM tutoring_group_students tgs
        JOIN tutoring_groups tg ON tgs.group_id = tg.group_id
        JOIN students s ON tgs.student_id = s.student_id
        LEFT JOIN assignment_submissions sub
            ON sub.student_id = s.student_id
            AND sub.assignment_id = :assignment_id
        WHERE tgs.group_id = :group_id
    ";

    $studentsParams = [
        ':group_id' => $selectedGroupId,
        ':assignment_id' => $selectedAssignmentId
    ];

    if ($isTeacher) {
        $studentsQuery .= " AND tg.teacher_id = :teacher_id ";
        $studentsParams[':teacher_id'] = $teacherId;
    }

    $studentsQuery .= " ORDER BY s.last_name, s.first_name";

    $studentsStmt = $pdo->prepare($studentsQuery);
    $studentsStmt->execute($studentsParams);
    $studentsResults = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// שליפת פירוט התשובות והמשוב של כל תלמיד בנפרד
$answersByStudent = [];

if ($selectedGroupId && $selectedAssignmentId) {
    $answersQuery = "
        SELECT 
            aa.student_id,
            aa.question_id,
            aa.selected_option,
            aa.is_correct,
            aa.feedback,
            aq.question_text,
            aq.option_a,
            aq.option_b,
            aq.option_c,
            aq.option_d,
            aq.correct_option
        FROM assignment_answers aa
        JOIN assignment_questions aq ON aa.question_id = aq.question_id
        JOIN assignments a ON aa.assignment_id = a.assignment_id
        JOIN tutoring_groups tg ON a.group_id = tg.group_id
        WHERE aa.assignment_id = :assignment_id
          AND a.group_id = :group_id
    ";

    $answersParams = [
        ':assignment_id' => $selectedAssignmentId,
        ':group_id' => $selectedGroupId
    ];

    if ($isTeacher) {
        $answersQuery .= " AND tg.teacher_id = :teacher_id ";
        $answersParams[':teacher_id'] = $teacherId;
    }

    $answersQuery .= " ORDER BY aa.student_id, aq.question_id";

    $answersStmt = $pdo->prepare($answersQuery);
    $answersStmt->execute($answersParams);
    $answers = $answersStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($answers as $answer) {
        $answersByStudent[$answer['student_id']][] = $answer;
    }
}

// פונקציית עזר להמרת אותיות האופציות מאנגלית לעברית
function optionToHebrew($option) {
    if ($option === 'a') return 'א';
    if ($option === 'b') return 'ב';
    if ($option === 'c') return 'ג';
    if ($option === 'd') return 'ד';
    return $option;
}

// פונקציית עזר לקבלת מלל התשובה המלא מתוך השאלה
function getOptionText($answer, $option) {
    if ($option === 'a') return $answer['option_a'];
    if ($option === 'b') return $answer['option_b'];
    if ($option === 'c') return $answer['option_c'];
    if ($option === 'd') return $answer['option_d'];
    return '';
}

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

function gradeToHebrewDisplay($gradeLevel) {
    $grade = (string)$gradeLevel;

    if ($grade === '10') return "שכבה י׳";
    if ($grade === '11') return "שכבה י״א";
    if ($grade === '12') return "שכבה י״ב";

    return "שכבה " . $grade;
}

$backUrl = $isTeacher ? 'teacher_dashboard.php' : 'index.php';
$backText = $isTeacher ? 'חזרה לסביבת מורה' : 'חזרה למסך הבית';
$logoutUrl = $isTeacher ? 'teacher_logout.php' : 'logout.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>מעקב הגשות תלמידים</title>

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
            background: rgba(252, 219, 209, 0.9);
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
            padding: 8px 16px;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 500;
            font-size: 13px;
            display: inline-block;
            transition: all 0.2s ease;
        }

        .logout:hover {
            background: #fff5f5;
            color: #c53030;
            border-color: #fed7d7;
            transform: translateY(-1px);
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.8);
            color: #718096;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            border: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 500;
            font-size: 13px;
            display: inline-block;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: #e2e8f0;
            color: #2d3748;
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
            max-width: 800px; 
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .card {
            background: #fff9e6 !important;
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important; 
            padding: 28px 22px !important;
            position: relative !important;
            border: 1px solid #f3ebd1 !important;
            width: 100% !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.01), 0 8px 20px rgba(80, 60, 40, 0.06) !important;
        }

        .card h2 {
            font-size: 18px;
            font-weight: 700;
            color: #333a42;
            margin-bottom: 12px;
            text-align: center;
        }

        label {
            display: block;
            margin-top: 14px;
            margin-bottom: 6px;
            font-weight: 500;
            color: #5a6578;
            font-size: 13px;
        }

        select {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 14px;
            background: #ffffff;
            color: #2d3748;
            outline: none;
            transition: all 0.2s ease;
        }

        select:focus {
            border-color: #8fa3db;
            box-shadow: 0 0 0 3px rgba(143, 163, 219, 0.15);
        }

        select:disabled {
            background: #edf2f7;
            color: #a0aec0;
            cursor: not-allowed;
        }

        .filter-note {
            font-size: 12px;
            color: #718096;
            margin-top: 8px;
            text-align: center;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #edf2f7;
        }

        th, td {
            border: 1px solid #edf2f7;
            padding: 12px 10px;
            text-align: center;
            font-size: 13px;
        }

        th {
            background: #1e3a5f;
            color: white;
            font-weight: 500;
        }

        .submitted {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            background: #e8f7ee;
            color: #0b7a35;
            font-weight: 500;
            font-size: 12px;
        }

        .not-submitted {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            background: #fff5f5;
            color: #c53030;
            font-weight: 500;
            font-size: 12px;
        }

        .details-btn {
            padding: 6px 12px;
            border: none;
            background: #8fa3db;
            color: white;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            font-size: 12px;
            box-shadow: 0 4px 10px rgba(143, 163, 219, 0.2);
            transition: all 0.2s ease;
        }

        .details-btn:hover {
            background: #7b8fc7;
            transform: translateY(-1px);
        }

        .details {
            display: none;
            margin-top: 12px;
            text-align: right;
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }

        .question-box {
            border-bottom: 1px solid #edf2f7;
            padding: 12px 0;
        }

        .question-box:last-child {
            border-bottom: none;
        }

        .correct { color: #2f855a; font-weight: 500; }
        .wrong { color: #c53030; font-weight: 500; }

        .empty {
            text-align: center;
            color: #718096;
            padding: 25px;
            font-size: 14px;
        }

        .notice {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
            border-radius: 12px;
            padding: 14px;
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 10px;
            width: 100%;
        }

        .summary-box {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.01);
        }

        .summary-box h3 {
            margin: 0;
            font-size: 26px;
            color: #2d3748;
            font-weight: 700;
        }

        .summary-box p {
            margin: 6px 0 0;
            color: #718096;
            font-size: 13px;
        }

        .full {
            grid-column: 1 / -1;
            width: 100% !important;
        }

        @media(max-width:768px){
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            body { padding: 0 10px 30px 10px; }
            .hero h1 { font-size: 28px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>

<body class="gradeup-ui gradeup-workspace">

    <div class="header-container">
        <div class="topbar">
            <div class="logo">GradeUp - מעקב פדגוגי</div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="<?= htmlspecialchars($backUrl) ?>" class="back-btn">
                    <?= htmlspecialchars($backText) ?>
                </a>

                <a class="logout" href="<?= htmlspecialchars($logoutUrl) ?>">יציאה</a>
            </div>
        </div>

        <div class="hero">
            <h1>מעקב הגשות תלמידים</h1>
            <p>
                <?php if ($isTeacher): ?>
                    מוצגות כאן רק קבוצות התגבור שאתה אחראי עליהן.
                <?php else: ?>
                    בחר שכבה, קבוצת תגבור ומשימה כדי לראות מי הגיש, מי לא הגיש, מה הציון ומה התלמיד ענה.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="app-container">

        <?php if ($permissionMessage !== ''): ?>
            <div class="card">
                <div class="notice"><?= htmlspecialchars($permissionMessage) ?></div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>סינון נתוני משימה</h2>
            
            <form method="GET" id="filterForm">
                <label>בחר שכבה</label>
                <select name="grade_level" id="gradeLevelSelect" onchange="onGradeChange(this.form)">
                    <option value="">-- בחר שכבה --</option>

                    <?php foreach($availableGrades as $grade): ?>
                        <option value="<?= htmlspecialchars($grade) ?>" <?= (string)$selectedGradeLevel === (string)$grade ? 'selected' : '' ?>>
                            <?= htmlspecialchars(gradeToHebrewDisplay($grade)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>בחר קבוצת תגבור</label>
                <select name="group_id" id="groupSelect" onchange="onGroupChange(this.form)" <?= $selectedGradeLevel === '' ? 'disabled' : '' ?>>
                    <option value="">-- בחר קבוצה --</option>

                    <?php foreach($groups as $group): ?>
                        <?php
                            $subjectDisplay = subjectToHebrewDisplay($group['subject_name']);
                            $groupGrade = (string)$group['grade_level'];
                            $hideGroup = ($selectedGradeLevel !== '' && $groupGrade !== (string)$selectedGradeLevel);
                        ?>

                        <option
                            value="<?= htmlspecialchars($group['group_id']) ?>"
                            data-grade="<?= htmlspecialchars($groupGrade) ?>"
                            <?= $hideGroup ? 'style="display:none;"' : '' ?>
                            <?= $selectedGroupId == $group['group_id'] ? 'selected' : '' ?>
                        >
                            קבוצה <?= htmlspecialchars($group['group_id']) ?> |
                            <?= htmlspecialchars($subjectDisplay) ?> |
                            <?= htmlspecialchars(gradeToHebrewDisplay($group['grade_level'])) ?> |
                            מורה: <?= htmlspecialchars($group['teacher_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="filter-note" id="filterNote">
                    <?php if ($selectedGradeLevel === ''): ?>
                        בחר שכבה כדי להציג את קבוצות התגבור המתאימות.
                    <?php else: ?>
                        מוצגות רק קבוצות תגבור של <?= htmlspecialchars(gradeToHebrewDisplay($selectedGradeLevel)) ?>.
                    <?php endif; ?>
                </div>

                <?php if ($selectedGroupId): ?>
                    <label style="margin-top:15px;">בחר משימה</label>
                    <select name="assignment_id" onchange="this.form.submit()">
                        <option value="">-- בחר משימה --</option>

                        <?php foreach($assignments as $assignment): ?>
                            <option value="<?= htmlspecialchars($assignment['assignment_id']) ?>" <?= $selectedAssignmentId == $assignment['assignment_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($assignment['title']) ?> | הגשה עד: <?= htmlspecialchars($assignment['due_date'] ?? 'לא הוגדר') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($groups) === 0): ?>
            <div class="card">
                <div class="empty">
                    <?php if ($isTeacher): ?>
                        אין לך כרגע קבוצות תגבור משויכות ולכן אין נתוני הגשות להצגה.
                    <?php else: ?>
                        אין כרגע קבוצות תגבור במערכת.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($selectedGroupId && count($assignments) === 0): ?>
            <div class="card">
                <div class="empty">לקבוצה שבחרת עדיין לא נוצרו משימות במערכת.</div>
            </div>
        <?php endif; ?>

        <?php if ($selectedGroupId && $selectedAssignmentId): ?>
            <?php
                $totalStudents = count($studentsResults);
                $submittedCount = 0;
                $scoreSum = 0;
                $scoreCount = 0;

                foreach ($studentsResults as $student) {
                    if (!empty($student['submission_id'])) {
                        $submittedCount++;

                        if ($student['ai_score'] !== null) {
                            $scoreSum += (int)$student['ai_score'];
                            $scoreCount++;
                        }
                    }
                }

                $notSubmittedCount = $totalStudents - $submittedCount;
                $averageScore = $scoreCount > 0 ? round($scoreSum / $scoreCount, 1) : 0;
            ?>

            <div class="card">
                <h2>סיכום הגשות סטטיסטי</h2>
                <div class="summary-grid">
                    <div class="summary-box">
                        <h3><?= htmlspecialchars($totalStudents) ?></h3>
                        <p>תלמידים בקבוצה</p>
                    </div>

                    <div class="summary-box">
                        <h3><?= htmlspecialchars($submittedCount) ?></h3>
                        <p>הגישו</p>
                    </div>

                    <div class="summary-box">
                        <h3><?= htmlspecialchars($notSubmittedCount) ?></h3>
                        <p>לא הגישו</p>
                    </div>

                    <div class="summary-box">
                        <h3><?= htmlspecialchars($averageScore) ?></h3>
                        <p>ממוצע ציונים</p>
                    </div>
                </div>
            </div>

            <div class="card full">
                <h2>פירוט הגשות תלמידים</h2>

                <div style="overflow-x: auto; width: 100%;">
                    <table>
                        <tr>
                            <th>תלמיד</th>
                            <th>כיתה</th>
                            <th>מייל</th>
                            <th>סטטוס</th>
                            <th>ציון</th>
                            <th>משוב כללי</th>
                            <th>פירוט תשובות</th>
                        </tr>

                        <?php foreach($studentsResults as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                <td><?= htmlspecialchars($student['class_name']) ?></td>
                                <td><?= htmlspecialchars($student['email']) ?></td>
                                <td>
                                    <?php if (!empty($student['submission_id'])): ?>
                                        <span class="submitted">הגיש</span>
                                    <?php else: ?>
                                        <span class="not-submitted">לא הגיש</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $student['ai_score'] !== null ? htmlspecialchars($student['ai_score']) : '-' ?></td>
                                <td><?= !empty($student['ai_feedback']) ? htmlspecialchars($student['ai_feedback']) : '-' ?></td>
                                <td>
                                    <?php if (!empty($student['submission_id'])): ?>
                                        <button class="details-btn" type="button" onclick="toggleDetails('details_<?= htmlspecialchars($student['student_id']) ?>')">
                                            הצג פירוט
                                        </button>

                                        <div class="details" id="details_<?= htmlspecialchars($student['student_id']) ?>">
                                            <?php if (!empty($answersByStudent[$student['student_id']])): ?>
                                                <?php foreach($answersByStudent[$student['student_id']] as $answer): ?>
                                                    <div class="question-box">
                                                        <strong>שאלה:</strong> <?= htmlspecialchars($answer['question_text']) ?><br><br>

                                                        <strong>התלמיד סימן:</strong>
                                                        <?= htmlspecialchars(optionToHebrew($answer['selected_option'])) ?>
                                                        —
                                                        <?= htmlspecialchars(getOptionText($answer, $answer['selected_option'])) ?><br>

                                                        <strong>תשובה נכונה:</strong>
                                                        <?= htmlspecialchars(optionToHebrew($answer['correct_option'])) ?>
                                                        —
                                                        <?= htmlspecialchars(getOptionText($answer, $answer['correct_option'])) ?><br>
                                                        
                                                        <?php if ((int)$answer['is_correct'] === 1): ?>
                                                            <span class="correct">נכון ✅</span>
                                                        <?php else: ?>
                                                            <span class="wrong">לא נכון ❌</span>
                                                        <?php endif; ?><br><br>
                                                        
                                                        <strong>משוב ה־AI:</strong> <?= htmlspecialchars($answer['feedback']) ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                לא נמצא פירוט תשובות במערכת.
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

<script>
function onGradeChange(form) {
    const groupSelect = document.getElementById('groupSelect');
    const assignmentSelect = form.querySelector('[name="assignment_id"]');

    if (groupSelect) {
        groupSelect.value = '';
    }

    if (assignmentSelect) {
        assignmentSelect.value = '';
    }

    form.submit();
}

function onGroupChange(form) {
    const assignmentSelect = form.querySelector('[name="assignment_id"]');

    if (assignmentSelect) {
        assignmentSelect.value = '';
    }

    form.submit();
}

function toggleDetails(id) {
    const box = document.getElementById(id);
    if (!box) return;

    if (box.style.display === 'block') {
        box.style.display = 'none';
    } else {
        box.style.display = 'block';
    }
}
</script>
</body>
</html>
