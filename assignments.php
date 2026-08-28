<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_config.php';

$groupsQuery = "
SELECT 
    tg.group_id,
    s.subject_name,
    tg.grade_level,
    CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
FROM tutoring_groups tg
JOIN subjects s ON tg.subject_id = s.subject_id
JOIN teachers t ON tg.teacher_id = t.teacher_id
ORDER BY tg.group_id DESC
";

$stmt = $pdo->prepare($groupsQuery);
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$assignmentsQuery = "
SELECT 
    a.assignment_id,
    a.title,
    a.description,
    a.due_date,
    a.created_at,
    s.subject_name,
    tg.grade_level,
    CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
    COUNT(q.question_id) AS question_count
FROM assignments a
JOIN tutoring_groups tg ON a.group_id = tg.group_id
JOIN subjects s ON tg.subject_id = s.subject_id
JOIN teachers t ON tg.teacher_id = t.teacher_id
LEFT JOIN assignment_questions q ON a.assignment_id = q.assignment_id
GROUP BY 
    a.assignment_id,
    a.title,
    a.description,
    a.due_date,
    a.created_at,
    s.subject_name,
    tg.grade_level,
    teacher_name
ORDER BY a.assignment_id DESC
";

$stmtAssignments = $pdo->prepare($assignmentsQuery);
$stmtAssignments->execute();
$assignments = $stmtAssignments->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>ניהול משימות אמריקאיות</title>

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
            background: #fcdbd1; /* רקע האפרסק מהמוקאפ */
            min-height: 100vh;
            color: #2d3748;
            padding: 40px 15px;
            display: block;
        }

        /* עטיפת האפליקציה הממורכזת והקומפקטית, בדיוק כמו שאר הדפים */
        .app-container {
            width: 100%;
            max-width: 440px; 
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* בר פעולות עליון */
        .top-actions {
            display: flex;
            justify-content: flex-start;
            width: 100%;
            margin-bottom: 10px;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.8);
            color: #718096;
            padding: 8px 16px;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: #e2e8f0;
            color: #2d3748;
        }

        /* כותרת הדף הממורכזת */
        .hero {
            text-align: center;
            margin-bottom: 25px;
            width: 100%;
        }

        .hero h1 {
            font-size: 34px;
            font-weight: 700;
            color: #4a5153;
            line-height: 1.2;
        }

        .subtitle {
            font-size: 14px;
            color: #718096;
            margin-top: 8px;
            opacity: 0.85;
            line-height: 1.5;
        }

        /* פתק הנייר הצהוב (Sticky Note) לטופס יצירת המשימה */
        .card {
            background: #fff9e6 !important;
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important; 
            padding: 28px 22px !important;
            position: relative;
            border: 1px solid #f3ebd1 !important;
            width: 100%;
            box-shadow: 
                0 2px 4px rgba(0,0,0,0.01),
                0 8px 20px rgba(80, 60, 40, 0.06);
            margin-bottom: 15px;
        }

        /* כרטיס טבלת המשימות */
        .table-card {
            background: #fff9e6 !important; /* הגוון הצהוב של הדפים */
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important; /* אפקט הקיפול העדין בפינה */
            padding: 28px 22px !important;
            position: relative;
            border: 1px solid #f3ebd1 !important;
            width: 100%;
            box-shadow: 
                0 2px 4px rgba(0,0,0,0.01),
                0 8px 20px rgba(80, 60, 40, 0.06);
            margin-bottom: 15px;
        }

        /* התאמת צבע כותרות הטבלה לרקע הצהוב */
        th {
            background: #1e3a5f !important; /* הצבע הכהה והיפה מהקוד המקורי שלך */
            color: white;
            font-weight: 500;
        }

        .card h2, .table-card h2 {
            font-size: 18px;
            font-weight: 700;
            color: #333a42;
            margin-bottom: 15px;
            text-align: center;
        }

        /* אלמנטים פנימיים בטופס */
        label {
            display: block;
            margin-top: 14px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #5a6578;
            font-size: 13px;
        }

        select, input {
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

        select:focus, input:focus {
            border-color: #8fa3db;
            box-shadow: 0 0 0 3px rgba(143, 163, 219, 0.15);
        }

        /* שינוי הגריד המקורי לטור כרטיסים צר ומדויק בתוך הטופס */
        .proposal-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 10px;
            width: 100%;
        }

        /* כרטיסי המשימות המוצעות - עיצוב לבן נקי ונוח ללחיצה */
        .proposal-card {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .proposal-card:hover {
            border-color: #8fa3db;
            box-shadow: 0 4px 14px rgba(143, 163, 219, 0.1);
        }

        .proposal-card.selected {
            border-color: #8fa3db;
            background: #f4f8ff;
            box-shadow: 0 4px 15px rgba(143, 163, 219, 0.15);
        }

        .proposal-card h3 {
            font-size: 15px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .proposal-card p {
            font-size: 13px;
            color: #718096;
            line-height: 1.4;
        }

        /* תיבת תצוגה מקדימה של שאלות */
        .questions-box {
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.6);
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 16px;
            display: none;
        }

        .questions-box h3 {
            font-size: 14px;
            color: #2d3748;
            margin-bottom: 12px;
            text-align: center;
        }

        .question {
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .question:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .question-title {
            font-weight: 700;
            color: #2d3748;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .option {
            font-size: 13px;
            color: #4a5568;
            margin: 4px 0;
        }

        .correct {
            color: #2f855a;
            font-weight: 500;
            font-size: 13px;
            margin-top: 4px;
        }

        /* כפתור הפעולה הראשי הסגול-כחול */
        .btn {
            display: block;
            width: 100%;
            padding: 13px;
            margin-top: 20px;
            background: #8fa3db; 
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(143, 163, 219, 0.35);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn:hover {
            background: #7b8fc7;
            box-shadow: 0 6px 16px rgba(143, 163, 219, 0.5);
            transform: translateY(-1px);
        }

        /* טבלאות היסטוריית משימות קיימות */
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
            
            /* כפייה נוקשה של הגופן גם על מספרים ותאריכים בתוך הטבלה */
            font-family: 'Rubik', sans-serif !important; 
        }

        th {
            background: #8fa3db;
            color: white;
            font-weight: 500;
        }

        tr:nth-child(even) {
            background: #fcfcfc;
        }

        /* רספונסיביות מלאה למובייל */
        @media (max-width: 480px) {
            body { padding: 20px 10px; }
            .card, .table-card { padding: 20px 15px !important; }
            .hero h1 { font-size: 28px; }
            th, td { padding: 8px 4px; font-size: 11px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>

<body class="gradeup-ui gradeup-workspace">

<div class="app-container">

    <div class="top-actions">
        <a href="index.php" class="back-btn">חזרה למסך הבית</a>
    </div>

    <div class="hero">
        <h1>ניהול משימות</h1>
        <p class="subtitle">
            בחרי קבוצת תגבור ומשימה מוצעת מהבנק, קבעי תאריך הגשה ושמרי אותה לתלמידים במערכת.
        </p>
    </div>

    <div class="card">
        <h2>יצירת משימה חדשה</h2>

        <label>בחר קבוצת תגבור</label>
        <select id="groupId">
            <option value="">-- בחר קבוצה --</option>
            <?php foreach($groups as $group): ?>
                <option value="<?= $group['group_id'] ?>">
                    קבוצה <?= $group['group_id'] ?> | <?= htmlspecialchars($group['subject_name']) ?> | שכבה <?= htmlspecialchars($group['grade_level']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>תאריך הגשה אחרון</label>
        <input type="date" id="dueDate">

        <label>בחר משימה מוצעת על ידי המערכת</label>
        <div class="proposal-grid" id="proposalGrid"></div>

        <div class="questions-box" id="questionsBox"></div>

        <button class="btn" onclick="saveSelectedAssignment()">
            שמור ושלח משימה לקבוצה
        </button>
    </div>

    <div class="table-card">
        <h2>משימות קיימות במערכת</h2>
        <div style="overflow-x: auto; width: 100%;">
            <table>
                <tr>
                    <th>ID</th>
                    <th>מקצוע</th>
                    <th>שכבה</th>
                    <th>כותרת</th>
                    <th>שאלות</th>
                    <th>תאריך הגשה</th>
                </tr>
                <?php foreach($assignments as $assignment): ?>
                    <tr>
                        <td><?= $assignment['assignment_id'] ?></td>
                        <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                        <td>שכבה <?= htmlspecialchars($assignment['grade_level']) ?></td>
                        <td><?= htmlspecialchars($assignment['title']) ?></td>
                        <td><strong><?= htmlspecialchars($assignment['question_count']) ?></strong></td>
                        <td><?= htmlspecialchars($assignment['due_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

</div>

<script>
// המערך המקורי שלך נשמר ללא שינוי פסיק
const suggestedAssignments = [
    {
        id: 1,
        title: 'דף עבודה אמריקאי - משולשים ישרי זווית',
        description: 'תרגול בסיסי בנושא משפט פיתגורס וזיהוי היתר במשולש ישר זווית.',
        questions: [
            {
                question_text: 'במשולש ישר זווית, מהו היתר?',
                option_a: 'הצלע הקצרה ביותר',
                option_b: 'הצלע שמול הזווית הישרה',
                option_c: 'אחת הניצבות',
                option_d: 'הגובה במשולש',
                correct_option: 'b',
                explanation: 'היתר הוא הצלע שמול הזווית הישרה, והוא הצלע הארוכה ביותר במשולש ישר זווית.'
            },
            {
                question_text: 'אם הניצבים הם 3 ו-4, מה אורך היתר?',
                option_a: '5',
                option_b: '6',
                option_c: '7',
                option_d: '12',
                correct_option: 'a',
                explanation: 'לפי משפט פיתגורס: 3² + 4² = 9 + 16 = 25, ולכן היתר הוא 5.'
            },
            {
                question_text: 'איזו נוסחה מתארת את משפט פיתגורס?',
                option_a: 'a + b = c',
                option_b: 'a² + b² = c²',
                option_c: 'a × b = c',
                option_d: 'a² - b² = c²',
                correct_option: 'b',
                explanation: 'במשולש ישר זווית סכום ריבועי הניצבים שווה לריבוע היתר.'
            }
        ]
    },
    {
        id: 2,
        title: 'דף עבודה אמריקאי - משוואות ליניאריות',
        description: 'תרגול פתרון משוואות ממעלה ראשונה עם נעלם אחד.',
        questions: [
            {
                question_text: 'מה הפתרון של המשוואה x + 5 = 12?',
                option_a: 'x = 5',
                option_b: 'x = 6',
                option_c: 'x = 7',
                option_d: 'x = 17',
                correct_option: 'c',
                explanation: 'מחסרים 5 משני האגפים: x = 12 - 5 = 7.'
            },
            {
                question_text: 'מה הפתרון של המשוואה 2x = 18?',
                option_a: 'x = 6',
                option_b: 'x = 8',
                option_c: 'x = 9',
                option_d: 'x = 18',
                correct_option: 'c',
                explanation: 'מחלקים את שני האגפים ב-2 ומקבלים x = 9.'
            },
            {
                question_text: 'מה הפתרון של המשוואה x - 4 = 10?',
                option_a: 'x = 6',
                option_b: 'x = 10',
                option_c: 'x = 14',
                option_d: 'x = -14',
                correct_option: 'c',
                explanation: 'מוסיפים 4 לשני האגפים: x = 14.'
            }
        ]
    },
    {
        id: 3,
        title: 'דף עבודה אמריקאי - אחוזים',
        description: 'תרגול חישובי אחוזים בסיסיים והבנת משמעות האחוז.',
        questions: [
            {
                question_text: 'כמה הם 10% מתוך 200?',
                option_a: '10',
                option_b: '20',
                option_c: '30',
                option_d: '40',
                correct_option: 'b',
                explanation: '10% הם עשירית מהכמות. עשירית מ-200 היא 20.'
            },
            {
                question_text: 'כמה הם 25% מתוך 80?',
                option_a: '10',
                option_b: '15',
                option_c: '20',
                option_d: '25',
                correct_option: 'c',
                explanation: '25% הם רבע. רבע מ-80 הוא 20.'
            },
            {
                question_text: 'אם מוצר עולה 100 ש״ח ויש הנחה של 20%, מה מחירו לאחר ההנחה?',
                option_a: '70',
                option_b: '75',
                option_c: '80',
                option_d: '90',
                correct_option: 'c',
                explanation: '20% מתוך 100 הם 20, ולכן המחיר לאחר ההנחה הוא 80.'
            }
        ]
    }
];

let selectedAssignment = null;

function renderProposals(){
    const proposalGrid = document.getElementById('proposalGrid');
    let html = '';

    suggestedAssignments.forEach(assignment => {
        html += `
            <div class="proposal-card" id="proposal_${assignment.id}" onclick="selectAssignment(${assignment.id})">
                <h3>${assignment.title}</h3>
                <p>${assignment.description}</p>
                <p style="margin-top: 4px; font-size: 12px; color: #8fa3db;"><strong>${assignment.questions.length}</strong> שאלות אמריקאיות</p>
            </div>
        `;
    });

    proposalGrid.innerHTML = html;
}

function selectAssignment(assignmentId){
    selectedAssignment = suggestedAssignments.find(a => a.id === assignmentId);

    document.querySelectorAll('.proposal-card').forEach(card => {
        card.classList.remove('selected');
    });

    document.getElementById('proposal_' + assignmentId).classList.add('selected');
    renderQuestionsPreview();
}

function renderQuestionsPreview(){
    if(!selectedAssignment){
        return;
    }

    const questionsBox = document.getElementById('questionsBox');
    let html = `<h3>תצוגה מקדימה של השאלות במשימה</h3>`;

    selectedAssignment.questions.forEach((q, index) => {
        html += `
            <div class="question">
                <div class="question-title">שאלה ${index + 1}: ${q.question_text}</div>
                <div class="option">א. ${q.option_a}</div>
                <div class="option">ב. ${q.option_b}</div>
                <div class="option">ג. ${q.option_c}</div>
                <div class="option">ד. ${q.option_d}</div>
                <div class="correct">תשובה נכונה: ${convertOptionToHebrew(q.correct_option)}</div>
                <div class="option" style="font-size: 12px; color:#718096; margin-top:2px;">הסבר: ${q.explanation}</div>
            </div>
        `;
    });

    questionsBox.innerHTML = html;
    questionsBox.style.display = 'block';
}

function convertOptionToHebrew(option){
    if(option === 'a') return 'א';
    if(option === 'b') return 'ב';
    if(option === 'c') return 'ג';
    if(option === 'd') return 'ד';
    return option;
}

async function saveSelectedAssignment(){
    const groupId = document.getElementById('groupId').value;
    const dueDate = document.getElementById('dueDate').value;

    if(!groupId){
        alert('יש לבחור קבוצת תגבור');
        return;
    }

    if(!selectedAssignment){
        alert('יש לבחור משימה מוצעת');
        return;
    }

    if(!dueDate){
        alert('יש לבחור תאריך הגשה');
        return;
    }

    const response = await fetch('api/save_assignment.php', {
        method:'POST',
        headers:{
            'Content-Type':'application/json'
        },
        body:JSON.stringify({
            group_id:groupId,
            title:selectedAssignment.title,
            description:selectedAssignment.description,
            due_date:dueDate,
            created_by:'<?= $_SESSION['user'] ?>',
            questions:selectedAssignment.questions
        })
    });

    const result = await response.json();

    if(result.success){
        alert('המשימה נשמרה בהצלחה ונשלחה לקבוצה ✅');
        location.reload();
    }else{
        alert('שגיאה: ' + result.message);
    }
}

renderProposals();
</script>

</body>
</html>
