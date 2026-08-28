<?php
session_start();

// חסימת גישה לתלמידים לא מחוברים
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

require_once 'db_config.php';

$studentId = (int)$_SESSION['student_id'];
$studentName = $_SESSION['student_name'] ?? 'תלמיד';

// שאילתת SQL לקבלת כל המשימות שמשויכות לקבוצות הלמידה של התלמיד הנוכחי
$assignmentsQuery = "
SELECT 
    a.assignment_id,
    a.group_id,
    a.title,
    a.description,
    a.due_date,
    a.created_at,
    s.subject_name,
    tg.grade_level,
    CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
    sub.ai_score,
    sub.ai_feedback,
    sub.status AS submission_status
FROM tutoring_group_students tgs
JOIN tutoring_groups tg ON tgs.group_id = tg.group_id
JOIN assignments a ON tg.group_id = a.group_id
JOIN subjects s ON tg.subject_id = s.subject_id
JOIN teachers t ON tg.teacher_id = t.teacher_id
LEFT JOIN assignment_submissions sub 
    ON sub.assignment_id = a.assignment_id 
    AND sub.student_id = tgs.student_id
WHERE tgs.student_id = :student_id
ORDER BY a.assignment_id DESC
";

$stmt = $pdo->prepare($assignmentsQuery);
$stmt->execute([
    ':student_id' => $studentId
]);

$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GradeUp - משימות תלמיד</title>

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

        /* סרגל ניווט צף עליון עם אפקט זכוכית */
        .header-container {
            width: 100%;
            max-width: 800px;
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

        /* כפתור יציאה מעודכן ואחיד למערכת */
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

        .topbar-actions {
            display: flex;
            gap: 8px;
            align-items: center;
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

        /* עטיפת הפתקים המרכזית */
        .app-container {
            width: 100%;
            max-width: 500px; 
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* פתק ממו צהוב מותאם */
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
            margin-bottom: 15px;
            text-align: center;
        }

        .assignment-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }

        /* כרטיס משימה פנימי לבן ונקי */
        .assignment-card {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .assignment-card:hover {
            border-color: #8fa3db;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(143, 163, 219, 0.08);
        }

        .assignment-card.selected {
            border-color: #8fa3db;
            background: #f4f8ff;
            box-shadow: 0 4px 14px rgba(143, 163, 219, 0.15);
        }

        .assignment-card h3 {
            font-size: 15px;
            color: #2d3748;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .meta {
            color: #718096;
            line-height: 1.5;
            font-size: 13px;
        }

        /* תגיות סטטוס של המשימות */
        .status-done {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 12px;
            border-radius: 20px;
            background: #e8f7ee;
            color: #0b7a35;
            font-weight: 500;
            font-size: 12px;
        }

        .status-open {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 12px;
            border-radius: 20px;
            background: #fff4e5;
            color: #a15c00;
            font-weight: 500;
            font-size: 12px;
        }

        /* קופסת שאילתה לתלמיד */
        .question {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 18px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.01);
        }

        .question h3 {
            font-size: 15px;
            color: #2d3748;
            margin-bottom: 12px;
            font-weight: 700;
        }


        .question-text {
            margin-bottom: 14px;
            font-size: 14px;
            color: #2d3748;
            line-height: 1.7;
            white-space: normal;
        }

        .code-block {
            direction: ltr;
            text-align: left;
            background: #1f2937;
            color: #f9fafb;
            border-radius: 12px;
            padding: 14px;
            margin: 10px 0;
            overflow-x: auto;
            white-space: pre;
            line-height: 1.6;
            font-family: Consolas, Monaco, 'Courier New', monospace !important;
            font-size: 13px;
        }

        .code-block code {
            font-family: Consolas, Monaco, 'Courier New', monospace !important;
        }

        /* שדות בחירה אמריקאיים נוחים ללחיצה */
        .option {
            display: flex !important;
            align-items: center;
            gap: 10px;
            margin: 8px 0 !important;
            padding: 12px 14px !important;
            background: #f8fafc !important;
            border-radius: 10px !important;
            border: 1px solid #e2e8f0 !important;
            cursor: pointer;
            font-size: 13px;
            font-weight: 400 !important;
            color: #4a5568;
            transition: all 0.15s ease;
        }

        .option:hover {
            background: #edf2f7 !important;
            border-color: #cbd5e1;
        }

        .option input[type="radio"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #8fa3db;
        }

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
            transition: all 0.2s ease;
            text-align: center;
        }

        .btn:hover {
            background: #7b8fc7;
            box-shadow: 0 6px 16px rgba(143, 163, 219, 0.5);
            transform: translateY(-1px);
        }

        /* פידבקים וקופסאות ציון */
        .result-box {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 16px;
            margin-top: 15px;
        }

        .result-box h3 {
            font-size: 15px;
            color: #2d3748;
            margin-bottom: 6px;
        }

        .result-box p {
            font-size: 13px;
            color: #4a5568;
            line-height: 1.4;
        }

        .success { color: #2f855a; font-weight: 700; }
        .wrong { color: #c53030; font-weight: 700; }

        .empty {
            text-align: center;
            color: #718096;
            padding: 30px;
            font-size: 15px;
        }

        @media(max-width: 520px) {
            body { padding: 0 10px 30px 10px; }
            .header-container { position: static; background: transparent; backdrop-filter: none; }
            .card { padding: 20px 15px !important; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=2">
    <link rel="stylesheet" href="assets/css/ui-polish.css?v=1">
</head>
<body class="gradeup-ui gradeup-workspace">

    <div class="header-container">
        <div class="topbar">
            <div class="logo">GradeUp - מרכז המשימות</div>

            <div class="topbar-actions">
                <a class="logout" href="profile_settings.php">הפרופיל שלי</a>
                <a class="logout" href="student_logout.php">יציאה</a>
            </div>
        </div>

        <div class="hero">
            <h1>שלום, <?= htmlspecialchars($studentName) ?></h1>
            <p>כאן מופיעות משימות התרגול הדינמיות ששלחו לך המורים.</p>
        </div>
    </div>

    <div class="app-container">

        <div class="card">
            <h2>המשימות שלי</h2>

            <?php if (count($assignments) === 0): ?>
                <div class="empty">אין לך כרגע משימות משויכות במערכת.</div>
            <?php else: ?>
                <div class="assignment-list">
                    <?php foreach($assignments as $assignment): ?>
                        <div class="assignment-card" id="assignment_card_<?= $assignment['assignment_id'] ?>" onclick="selectAssignment(<?= $assignment['assignment_id'] ?>)">
                            <h3><?= htmlspecialchars($assignment['title']) ?></h3>
                            <div class="meta">
                                מקצוע: <strong><?= htmlspecialchars($assignment['subject_name']) ?></strong><br>
                                מורה: <?= htmlspecialchars($assignment['teacher_name']) ?><br>
                                הגשה עד: <?= htmlspecialchars($assignment['due_date'] ?? 'לא הוגדר') ?>
                            </div>

                            <?php if (!empty($assignment['submission_status'])): ?>
                                <div class="status-done">
                                    ✅ הוגש | ציון: <?= htmlspecialchars($assignment['ai_score']) ?>
                                </div>
                            <?php else: ?>
                                <div class="status-open">
                                    📝 ממתין לפתרון שלך
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" id="questionsArea" style="display:none;"></div>

        <div class="card" id="resultArea" style="display:none;"></div>

    </div>

<script>
const studentId = <?= json_encode($studentId) ?>;
let selectedAssignmentId = null;


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

function formatPlainText(value) {
    return escapeHtml(value).replace(/\n/g, '<br>');
}

function formatTextWithCode(value) {
    const text = String(value || '');
    const regex = /```(?:[a-zA-Z0-9_+\-#]*)?\s*\n?([\s\S]*?)```/g;

    let html = '';
    let lastIndex = 0;
    let match;

    while ((match = regex.exec(text)) !== null) {
        const before = text.slice(lastIndex, match.index);
        const code = match[1] || '';

        html += formatPlainText(before);
        html += `<pre class="code-block"><code>${escapeHtml(code.trim())}</code></pre>`;

        lastIndex = regex.lastIndex;
    }

    html += formatPlainText(text.slice(lastIndex));

    return html;
}

// סימון ויזואלי של כרטיס המשימה שנבחר
function selectAssignment(assignmentId){
    selectedAssignmentId = assignmentId;

    document.querySelectorAll('.assignment-card').forEach(card => {
        card.classList.remove('selected');
    });

    const selectedCard = document.getElementById('assignment_card_' + assignmentId);
    if(selectedCard){
        selectedCard.classList.add('selected');
    }

    loadQuestions();
}

// משיכת השאלות האמריקאיות בצורה דינמית מהשרת
async function loadQuestions(){
    if(!selectedAssignmentId){ alert('יש לבחור משימה'); return; }

    const response = await fetch('api/get_assignment_questions.php?assignment_id=' + selectedAssignmentId);
    const result = await response.json();

    if(!result.success){ alert('שגיאה בטעינת שאלות: ' + result.message); return; }

    const questions = result.questions;
    if(questions.length === 0){ alert('אין שאלות למשימה זו'); return; }

    let html = `
        <h2>פתרון המשימה</h2>
        <p class="note">בחר תשובה אחת נכונה לכל שאלה, ובסיום לחץ על כפתור השליחה לבדיקת ה־AI.</p>
    `;

    questions.forEach((q, index) => {
        html += `
            <div class="question" data-question-id="${q.question_id}">
                <h3>שאלה ${index + 1}</h3>
                <div class="question-text">${formatTextWithCode(q.question_text)}</div>

                <label class="option">
                    <input type="radio" name="question_${q.question_id}" value="a">
                    <span>א. ${escapeHtml(q.option_a)}</span>
                </label>

                <label class="option">
                    <input type="radio" name="question_${q.question_id}" value="b">
                    <span>ב. ${escapeHtml(q.option_b)}</span>
                </label>

                <label class="option">
                    <input type="radio" name="question_${q.question_id}" value="c">
                    <span>ג. ${escapeHtml(q.option_c)}</span>
                </label>

                <label class="option">
                    <input type="radio" name="question_${q.question_id}" value="d">
                    <span>ד. ${escapeHtml(q.option_d)}</span>
                </label>
            </div>
        `;
    });

    html += `<button class="btn" onclick="submitAnswers()">שלח לבדיקת ה־AI</button>`;

    const questionsArea = document.getElementById('questionsArea');
    questionsArea.innerHTML = html;
    questionsArea.style.display = 'block';

    document.getElementById('resultArea').style.display = 'none';
    questionsArea.scrollIntoView({ behavior:'smooth' });
}

// שליחת התשובות שפתר התלמיד לבדיקת מודול ה-AI
async function submitAnswers(){
    if(!selectedAssignmentId){ alert('יש לבחור משימה'); return; }

    const questionDivs = document.querySelectorAll('[data-question-id]');
    const answers = [];

    for(const questionDiv of questionDivs){
        const questionId = questionDiv.getAttribute('data-question-id');
        const selected = questionDiv.querySelector('input[type="radio"]:checked');

        if(!selected){ alert('יש לענות על כל השאלות לפני שליחה לבדיקה'); return; }

        answers.push({
            question_id: questionId,
            selected_option: selected.value
        });
    }

    const response = await fetch('api/submit_assignment_answers.php', {
        method:'POST',
        headers:{ 'Content-Type':'application/json' },
        body:JSON.stringify({
            assignment_id:selectedAssignmentId,
            student_id:studentId,
            answers:answers
        })
    });

    const result = await response.json();
    if(!result.success){ alert('שגיאה בבדיקת תשובות: ' + result.message); return; }

    let html = `
        <h2>תוצאות הבדיקה</h2>
        <div class="result-box" style="background: #f0fff4; border-color: #c6f6d5; text-align:center;">
            <h3 style="font-size:22px; color:#2f855a;">ציון סופי: ${result.score}</h3>
            <p style="margin-top:5px; font-weight:500;">${result.general_feedback}</p>
        </div>
    `;

    result.feedback.forEach((item, index) => {
        html += `
            <div class="result-box">
                <h3>שאלה ${index + 1}</h3>
                <div class="question-text" style="margin-bottom:6px;">${formatTextWithCode(item.question_text)}</div>
                <p style="font-size:12px; color:#718096;">התשובה שסימנת: ${convertOptionToHebrew(item.selected_option)}</p>
                <p class="${item.is_correct == 1 ? 'success' : 'wrong'}" style="margin:4px 0;">
                    ${item.is_correct == 1 ? 'נכון מאד ✅' : 'טעון שיפור ❌'}
                </p>
                <p style="font-size:12px; background:#f7fafc; padding:8px; border-radius:6px; margin-top:4px;"><strong>הסבר ה־AI:</strong> ${escapeHtml(item.feedback)}</p>
            </div>
        `;
    });

    const resultArea = document.getElementById('resultArea');
    resultArea.innerHTML = html;
    resultArea.style.display = 'block';
    resultArea.scrollIntoView({ behavior:'smooth' });
}

function convertOptionToHebrew(option){
    if(option === 'a') return 'א';
    if(option === 'b') return 'ב';
    if(option === 'c') return 'ג';
    if(option === 'd') return 'ד';
    return option;
}
</script>
</body>
</html>
