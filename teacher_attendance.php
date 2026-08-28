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
    
    <title>GradeUp - ניהול נוכחות</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

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

        .top-link {
            background: rgba(255, 255, 255, 0.8);
            color: #718096;
            padding: 7px 14px;
            border-radius: 10px;
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
            content: '📋';
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
            max-width: 620px;
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

        label {
            display: block;
            margin-top: 12px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #5a6578;
            font-size: 13px;
        }

        .required {
            color: #c53030;
            font-weight: 700;
            margin-right: 4px;
        }

        select, input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            background: #ffffff;
            color: #2d3748;
            outline: none;
            transition: all 0.2s ease;
        }

        select:focus, input:focus {
            border-color: #8fa3db;
            box-shadow: 0 0 0 3px rgba(143, 163, 219, 0.15);
        }

        input:disabled {
            background: #edf2f7;
            color: #a0aec0;
            cursor: not-allowed;
        }

        .date-help {
            margin-top: 7px;
            font-size: 12px;
            color: #718096;
            line-height: 1.5;
            display: none;
        }

        .date-help strong {
            color: #4a5153;
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

        .btn.green {
            background: #48bb78 !important;
            box-shadow: 0 4px 10px rgba(72, 187, 120, 0.28) !important;
        }

        .btn.green:hover {
            background: #38a169 !important;
        }

        .buttons-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 15px;
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
            padding: 10px;
            text-align: center;
            font-size: 13px;
            color: #4a5568;
        }

        th {
            background: #8fa3db;
            color: white;
            font-weight: 500;
        }

        td input[type="radio"] {
            width: auto;
            transform: scale(1.1);
            cursor: pointer;
        }

        .empty {
            text-align: center;
            padding: 20px;
            color: #718096;
            font-size: 14px;
        }

        .success-box {
            display: none;
            background: rgba(255,255,255,0.65);
            border: 2px dashed #9ae6b4;
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            margin-top: 20px;
        }

        .success-box h3 {
            color: #2f855a;
            font-size: 17px;
            margin-bottom: 8px;
        }

        .success-box p {
            color: #718096;
            font-size: 13px;
            line-height: 1.6;
        }

        .error-box {
            display: none;
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
            border-radius: 12px;
            padding: 12px;
            margin-top: 15px;
            text-align: center;
            font-size: 13px;
            line-height: 1.6;
        }

        .group-item {
            background: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px solid #edf2f7;
            font-size: 13px;
        }

        .group-item span {
            font-size: 11px;
            color: #a0aec0;
        }

        .flatpickr-calendar {
            direction: rtl;
            border-radius: 16px;
            border: 1px solid #edf2f7;
            box-shadow: 0 12px 30px rgba(80, 60, 40, 0.14);
            overflow: hidden;
        }

        .flatpickr-day {
            border-radius: 10px;
        }

        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange {
            background: #8fa3db !important;
            border-color: #8fa3db !important;
        }

        .flatpickr-day.disabled,
        .flatpickr-day.disabled:hover {
            color: #cbd5e1 !important;
            background: #f7fafc !important;
            cursor: not-allowed !important;
        }

        .flatpickr-day:not(.disabled):hover {
            background: #edf2f7;
            border-color: #edf2f7;
        }

        @media (max-width: 1050px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
                max-width: 620px;
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

            .buttons-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>

<body class="gradeup-ui gradeup-workspace">

<div class="header-container">
    <div class="topbar">
        <div class="logo">GradeUp - ניהול נוכחות</div>

        <div class="topbar-actions">
            <a class="top-link" href="teacher_dashboard.php">חזרה לדף הבית</a>
            <a class="top-link" href="teacher_logout.php">יציאה</a>
        </div>
    </div>

    <div class="hero">
        <h1>ניהול נוכחות</h1>
        <p>בחר קבוצת תגבור, הזן תאריך ונושא מפגש, סמן נוכחות ושמור למערכת.</p>
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
                <div class="empty">אין לך כרגע קבוצות תגבור משויכות ולכן לא ניתן לסמן נוכחות.</div>
                <a class="btn gray" href="teacher_dashboard.php">חזרה לדף הבית</a>
            </div>

        <?php else: ?>

            <div class="card" id="attendanceFormCard">
                <h2>פרטי מפגש</h2>
                <p class="note">
                    בחר קבוצה שאתה אחראי עליה. לאחר בחירת הקבוצה, לוח השנה יאפשר בחירה רק ביום שבו הקבוצה מתקיימת.
                </p>

                <label>בחר קבוצת תגבור <span class="required">*</span></label>
                <select id="groupSelect">
                    <option value="">-- בחר קבוצה --</option>
                    <?php foreach($groups as $group): ?>
                        <?php
                            $subjectDisplay = $subjectMap[$group['subject_name']] ?? $group['subject_name'];
                            $dayHebrew = $dayMapHebrew[$group['day_of_week']] ?? $group['day_of_week'];
                            $startTime = substr($group['start_time'], 0, 5);
                            $endTime = substr($group['end_time'], 0, 5);
                        ?>
                        <option
                            value="<?= htmlspecialchars($group['group_id']) ?>"
                            data-day="<?= htmlspecialchars($group['day_of_week']) ?>"
                            data-day-hebrew="<?= htmlspecialchars($dayHebrew) ?>"
                            data-start-time="<?= htmlspecialchars($startTime) ?>"
                            data-end-time="<?= htmlspecialchars($endTime) ?>"
                        >
                            קבוצה <?= htmlspecialchars($group['group_id']) ?> |
                            <?= htmlspecialchars($subjectDisplay) ?> |
                            שכבה <?= htmlspecialchars($group['grade_level']) ?> |
                            יום <?= htmlspecialchars($dayHebrew) ?> <?= htmlspecialchars($startTime) ?>–<?= htmlspecialchars($endTime) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>תאריך מפגש <span class="required">*</span></label>
                <input type="text" id="sessionDate" placeholder="בחר קודם קבוצת תגבור" disabled>
                <div class="date-help" id="dateHelp"></div>

                <label>נושא המפגש <span class="required">*</span></label>
                <input type="text" id="topic" placeholder="למשל: פתרון מערכת משוואות / חזרה למבחן / לולאות">

                <button class="btn" onclick="loadStudents()">טען תלמידים לנוכחות</button>

                <div class="error-box" id="errorBox"></div>
            </div>

            <div class="card" id="studentsArea" style="display:none;"></div>

            <div class="card" id="successCard" style="display:none;">
                <div class="success-box" style="display:block;">
                    <h3>הנוכחות נשמרה בהצלחה ✅</h3>
                    <p>
                        כעת אפשר להמשיך ליצירת משימה עבור הקבוצה, או לחזור לדף הבית של סביבת המורה.
                    </p>

                    <div class="buttons-row">
                        <a class="btn green" href="teacher_assignments.php">מעבר ליצירת משימה</a>
                        <a class="btn gray" href="teacher_dashboard.php">חזרה לדף הבית</a>
                    </div>
                </div>
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
                    <div class="group-item">
                        <strong>קבוצה <?= htmlspecialchars($group['group_id']) ?></strong><br>
                        <?= htmlspecialchars($subjectDisplay) ?> | שכבה <?= htmlspecialchars($group['grade_level']) ?><br>
                        <span>
                            יום <?= htmlspecialchars($dayHebrew) ?> ב-<?= substr($group['start_time'], 0, 5) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/he.js"></script>

<script>
const dayNameToIndex = {
    'Sunday': 0,
    'Monday': 1,
    'Tuesday': 2,
    'Wednesday': 3,
    'Thursday': 4,
    'Friday': 5,
    'Saturday': 6
};

let selectedGroupDay = null;
let selectedGroupDayHebrew = null;
let selectedGroupStartTime = null;
let selectedGroupEndTime = null;
let datePicker = null;

document.addEventListener('DOMContentLoaded', function () {
    datePicker = flatpickr("#sessionDate", {
        locale: "he",
        dateFormat: "Y-m-d",
        allowInput: false,
        disableMobile: true,
        disable: [
            function(date) {
                if (!selectedGroupDay || dayNameToIndex[selectedGroupDay] === undefined) {
                    return true;
                }

                return date.getDay() !== dayNameToIndex[selectedGroupDay];
            }
        ],
        onChange: function(selectedDates, dateStr) {
            clearError();

            if (dateStr && !isSelectedDateValidForGroup()) {
                showError('התאריך שנבחר אינו תואם ליום שבו הקבוצה מתקיימת.');
                datePicker.clear();
            }
        }
    });

    document.getElementById('groupSelect').addEventListener('change', handleGroupChange);
});

function handleGroupChange() {
    clearError();

    const select = document.getElementById('groupSelect');
    const selectedOption = select.options[select.selectedIndex];
    const sessionDateInput = document.getElementById('sessionDate');
    const dateHelp = document.getElementById('dateHelp');

    selectedGroupDay = selectedOption.getAttribute('data-day');
    selectedGroupDayHebrew = selectedOption.getAttribute('data-day-hebrew');
    selectedGroupStartTime = selectedOption.getAttribute('data-start-time');
    selectedGroupEndTime = selectedOption.getAttribute('data-end-time');

    document.getElementById('studentsArea').style.display = 'none';
    document.getElementById('studentsArea').innerHTML = '';

    if (!select.value) {
        selectedGroupDay = null;
        selectedGroupDayHebrew = null;
        selectedGroupStartTime = null;
        selectedGroupEndTime = null;

        datePicker.clear();
        sessionDateInput.disabled = true;
        sessionDateInput.placeholder = 'בחר קודם קבוצת תגבור';
        dateHelp.style.display = 'none';
        dateHelp.innerHTML = '';
        datePicker.set('disable', [function(date) { return true; }]);
        return;
    }

    datePicker.clear();
    sessionDateInput.disabled = false;
    sessionDateInput.placeholder = 'בחר תאריך מפגש';

    datePicker.set('disable', [
        function(date) {
            if (!selectedGroupDay || dayNameToIndex[selectedGroupDay] === undefined) {
                return true;
            }

            return date.getDay() !== dayNameToIndex[selectedGroupDay];
        }
    ]);

    dateHelp.style.display = 'block';
    dateHelp.innerHTML = `
        ניתן לבחור רק תאריכים שחלים ביום <strong>${selectedGroupDayHebrew}</strong>,
        בהתאם למועד הקבוצה: <strong>${selectedGroupStartTime}–${selectedGroupEndTime}</strong>.
    `;

    setTimeout(() => {
        datePicker.open();
    }, 100);
}

function parseDateFromYmd(dateStr) {
    const parts = dateStr.split('-');

    if (parts.length !== 3) {
        return null;
    }

    const year = Number(parts[0]);
    const month = Number(parts[1]) - 1;
    const day = Number(parts[2]);

    if (!year || month < 0 || month > 11 || !day) {
        return null;
    }

    return new Date(year, month, day);
}

function isSelectedDateValidForGroup() {
    const sessionDate = document.getElementById('sessionDate').value;

    if (!sessionDate || !selectedGroupDay || dayNameToIndex[selectedGroupDay] === undefined) {
        return false;
    }

    const dateObject = parseDateFromYmd(sessionDate);

    if (!dateObject) {
        return false;
    }

    return dateObject.getDay() === dayNameToIndex[selectedGroupDay];
}

function showError(message){
    const errorBox = document.getElementById('errorBox');
    errorBox.innerText = message;
    errorBox.style.display = 'block';
}

function clearError(){
    const errorBox = document.getElementById('errorBox');
    errorBox.innerText = '';
    errorBox.style.display = 'none';
}

async function loadStudents(){
    clearError();

    const groupId = document.getElementById('groupSelect').value;
    const sessionDate = document.getElementById('sessionDate').value;
    const topic = document.getElementById('topic').value.trim();

    if(!groupId || !sessionDate || !topic){
        showError('יש למלא את כל שדות החובה: קבוצה, תאריך ונושא מפגש.');
        return;
    }

    if (!isSelectedDateValidForGroup()) {
        showError('התאריך שבחרת אינו מתאים ליום שבו הקבוצה מתקיימת. ניתן לבחור רק יום ' + selectedGroupDayHebrew + '.');
        return;
    }

    try {
        const response = await fetch('api/get_group_students.php?group_id=' + encodeURIComponent(groupId));
        const students = await response.json();

        if(!Array.isArray(students)){
            showError('שגיאה בטעינת תלמידים. נסה שוב.');
            return;
        }

        const studentsArea = document.getElementById('studentsArea');
        studentsArea.style.display = 'block';

        if(students.length === 0){
            studentsArea.innerHTML = '<div class="empty">אין תלמידים בקבוצה זו.</div>';
            return;
        }

        let html = `
            <h2>סימון נוכחות</h2>
            <p class="note">סמן לכל תלמיד האם הוא נוכח, מאחר או חסר. ברירת המחדל היא נוכח.</p>

            <table>
                <tr>
                    <th>תלמיד</th>
                    <th>כיתה</th>
                    <th>נוכח</th>
                    <th>מאחר</th>
                    <th>חסר</th>
                </tr>
        `;

        students.forEach(student => {
            html += `
                <tr data-student-id="${student.student_id}">
                    <td>${escapeHtml(student.first_name)} ${escapeHtml(student.last_name)}</td>
                    <td>${escapeHtml(student.class_name)}</td>
                    <td><input type="radio" name="attendance_${student.student_id}" value="present" checked></td>
                    <td><input type="radio" name="attendance_${student.student_id}" value="late"></td>
                    <td><input type="radio" name="attendance_${student.student_id}" value="absent"></td>
                </tr>
            `;
        });

        html += `
            </table>
            <button class="btn" onclick="saveAttendance()">שמור נוכחות</button>
        `;

        studentsArea.innerHTML = html;

        studentsArea.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

    } catch (error) {
        showError('שגיאה בתקשורת עם השרת בזמן טעינת התלמידים.');
    }
}

async function saveAttendance(){
    clearError();

    const groupId = document.getElementById('groupSelect').value;
    const sessionDate = document.getElementById('sessionDate').value;
    const topic = document.getElementById('topic').value.trim();

    if(!groupId || !sessionDate || !topic){
        showError('יש למלא את כל שדות החובה לפני שמירת הנוכחות.');
        return;
    }

    if (!isSelectedDateValidForGroup()) {
        showError('לא ניתן לשמור נוכחות בתאריך שאינו תואם ליום הקבוע של הקבוצה.');
        return;
    }

    const rows = document.querySelectorAll('[data-student-id]');
    const attendance = [];

    if(rows.length === 0){
        showError('לא נטענו תלמידים לנוכחות.');
        return;
    }

    rows.forEach(row => {
        const studentId = row.getAttribute('data-student-id');
        const selectedStatus = row.querySelector('input[type="radio"]:checked').value;

        attendance.push({
            student_id: studentId,
            status: selectedStatus
        });
    });

    try {
        const response = await fetch('api/save_attendance.php', {
            method:'POST',
            headers:{
                'Content-Type':'application/json'
            },
            body:JSON.stringify({
                group_id: groupId,
                session_date: sessionDate,
                topic: topic,
                attendance: attendance
            })
        });

        const result = await response.json();

        if(result.success){

            const mailResults = result.mail_results || {};
            const parentEmailsSent = mailResults.parent_absence_emails_sent || 0;
            const failedEmails = Array.isArray(mailResults.failed_emails) ? mailResults.failed_emails : [];
            const failedEmailsCount = failedEmails.length;

            let mailMessage = '';

            if (parentEmailsSent > 0) {
                mailMessage += `
                    <p style="margin-top:10px; color:#2f855a; font-weight:500;">
                        נשלחו ${parentEmailsSent} מיילים להורי תלמידים שסומנו כחסרים ✅
                    </p>
                `;
            } else {
                mailMessage += `
                    <p style="margin-top:10px; color:#718096;">
                        לא נשלחו מיילים להורים, כי לא סומנו תלמידים כחסרים.
                    </p>
                `;
            }

            if (failedEmailsCount > 0) {
                mailMessage += `
                    <p style="margin-top:8px; color:#c53030; font-size:13px;">
                        שים לב: היו ${failedEmailsCount} מיילים שלא נשלחו. ניתן לבדוק את פרטי השגיאה בלוגים.
                    </p>
                `;
            }

            document.getElementById('attendanceFormCard').style.display = 'none';
            document.getElementById('studentsArea').style.display = 'none';

            const successCard = document.getElementById('successCard');

            successCard.innerHTML = `
                <div class="success-box" style="display:block;">
                    <h3>הנוכחות נשמרה בהצלחה ✅</h3>

                    <p>
                        המפגש נשמר במערכת, כולל סימון נוכחות התלמידים.
                    </p>

                    ${mailMessage}

                    <div class="buttons-row">
                        <a class="btn green" href="teacher_assignments.php?group_id=${encodeURIComponent(groupId)}&topic=${encodeURIComponent(topic)}">
                            מעבר ליצירת משימה לקבוצה
                        </a>

                        <a class="btn gray" href="teacher_dashboard.php">
                            חזרה לדף הבית
                        </a>
                    </div>
                </div>
            `;

            successCard.style.display = 'block';

            successCard.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        } else {
            showError('שגיאה בשמירת נוכחות: ' + result.message);
        }

    } catch (error) {
        showError('שגיאה בתקשורת עם השרת בזמן שמירת הנוכחות.');
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
