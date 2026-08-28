<?php
require_once 'db_config.php';

$groupsQuery = "
SELECT 
    tg.group_id,
    s.subject_name,
    tg.grade_level,
    CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
    tg.day_of_week,
    tg.start_time,
    tg.end_time,
    tg.status
FROM tutoring_groups tg
JOIN subjects s ON tg.subject_id = s.subject_id
JOIN teachers t ON tg.teacher_id = t.teacher_id
ORDER BY tg.group_id DESC
";

$stmt = $pdo->prepare($groupsQuery);
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול נוכחות תגבורים</title>

    <style>
        body{
            font-family: Arial, sans-serif;
            background:#f4f6f9;
            padding:40px;
        }

        h1{
            color:#1e3a5f;
            text-align:right;
        }

        .card{
            background:white;
            padding:25px;
            border-radius:14px;
            margin-bottom:20px;
            box-shadow:0 2px 12px rgba(0,0,0,0.1);
        }

        label{
            display:block;
            margin-top:12px;
            font-weight:bold;
        }

        select,input,button{
            padding:12px;
            margin-top:8px;
            width:100%;
            box-sizing:border-box;
            font-size:16px;
        }

        table{
            width:100%;
            border-collapse:collapse;
            margin-top:20px;
            background:white;
        }

        th,td{
            border:1px solid #ddd;
            padding:12px;
            text-align:center;
        }

        th{
            background:#1e3a5f;
            color:white;
        }

        .btn{
            background:#1e3a5f;
            color:white;
            border:none;
            cursor:pointer;
            font-weight:bold;
            border-radius:6px;
        }

        .btn:hover{
            background:#163047;
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>

<body class="gradeup-ui gradeup-workspace">

<h1>ניהול נוכחות תגבורים</h1>

<div class="card">

    <label>בחר קבוצת תגבור:</label>

    <select id="groupSelect">
        <option value="">-- בחר קבוצה --</option>

        <?php foreach($groups as $group): ?>
            <option value="<?= $group['group_id'] ?>">
                קבוצה <?= $group['group_id'] ?>
                | <?= htmlspecialchars($group['subject_name']) ?>
                | שכבה <?= htmlspecialchars($group['grade_level']) ?>
                | מורה: <?= htmlspecialchars($group['teacher_name']) ?>
                | <?= htmlspecialchars($group['day_of_week']) ?>
                | <?= substr($group['start_time'], 0, 5) ?>
                | סטטוס: <?= htmlspecialchars($group['status']) ?>
            </option>
        <?php endforeach; ?>

    </select>

    <label>תאריך מפגש:</label>
    <input type="date" id="sessionDate">

    <label>נושא המפגש:</label>
    <input type="text" id="topic" placeholder="למשל: פונקציות טריגונומטריות">

    <button class="btn" onclick="loadStudents()">טען תלמידים</button>

</div>

<div id="studentsArea"></div>

<script>
async function loadStudents(){

    const groupId = document.getElementById('groupSelect').value;

    if(!groupId){
        alert('יש לבחור קבוצה');
        return;
    }

    const response = await fetch('api/get_group_students.php?group_id=' + groupId);
    const students = await response.json();

    console.log(students);

    if(!Array.isArray(students)){
        alert('שגיאה בטעינת תלמידים');
        return;
    }

    if(students.length === 0){
        document.getElementById('studentsArea').innerHTML =
            '<div class="card">אין תלמידים בקבוצה זו.</div>';
        return;
    }

    let html = `
        <div class="card">
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
                <td>${student.first_name} ${student.last_name}</td>
                <td>${student.class_name}</td>

                <td>
                    <input type="radio" name="attendance_${student.student_id}" value="present" checked>
                </td>

                <td>
                    <input type="radio" name="attendance_${student.student_id}" value="late">
                </td>

                <td>
                    <input type="radio" name="attendance_${student.student_id}" value="absent">
                </td>
            </tr>
        `;
    });

    html += `
        </table>
        <br>
        <button class="btn" onclick="saveAttendance()">שמור נוכחות</button>
        </div>
    `;

    document.getElementById('studentsArea').innerHTML = html;
}

async function saveAttendance(){

    const groupId = document.getElementById('groupSelect').value;
    const sessionDate = document.getElementById('sessionDate').value;
    const topic = document.getElementById('topic').value;

    if(!groupId){
        alert('יש לבחור קבוצת תגבור');
        return;
    }

    if(!sessionDate){
        alert('יש לבחור תאריך מפגש');
        return;
    }

    if(!topic){
        alert('יש להזין נושא מפגש');
        return;
    }

    const rows = document.querySelectorAll('[data-student-id]');
    const attendance = [];

    rows.forEach(row => {
        const studentId = row.getAttribute('data-student-id');
        const selectedStatus = row.querySelector('input[type="radio"]:checked').value;

        attendance.push({
            student_id: studentId,
            status: selectedStatus
        });
    });

    if(attendance.length === 0){
        alert('אין תלמידים לשמירת נוכחות');
        return;
    }

    const response = await fetch('api/save_attendance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            group_id: groupId,
            session_date: sessionDate,
            topic: topic,
            attendance: attendance
        })
    });

    const result = await response.json();

    if(result.success){
        alert('הנוכחות נשמרה בהצלחה ✅');
    } else {
        alert('שגיאה בשמירת נוכחות: ' + result.message);
    }
}
</script>

</body>
</html>
