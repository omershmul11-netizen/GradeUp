<?php
session_start();

if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit;
}

require_once 'db_config.php';
require_once __DIR__ . '/bagrut471/curriculum_catalog.php';

$teacherId = (int)$_SESSION['teacher_id'];
$teacherName = $_SESSION['teacher_name'] ?? 'מורה';

$prefillGroupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$prefillTopic = isset($_GET['topic']) ? trim($_GET['topic']) : '';

$groupsQuery = "
SELECT 
    tg.group_id,
    s.subject_name,
    tg.grade_level,
    tg.study_units,
    tg.day_of_week,
    tg.start_time,
    tg.end_time,
    tg.status
FROM tutoring_groups tg
JOIN subjects s ON tg.subject_id = s.subject_id
WHERE tg.teacher_id = :teacher_id
ORDER BY tg.group_id DESC
";

$stmt = $pdo->prepare($groupsQuery);
$stmt->execute([
    ':teacher_id' => $teacherId
]);

$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
$curriculumCatalog = gradeup_curriculum_catalog();

$assignmentsQuery = "
SELECT 
    a.assignment_id,
    a.title,
    a.description,
    a.due_date,
    a.created_at,
    a.assignment_type,
    a.worksheet_pdf_path,
    s.subject_name,
    tg.grade_level,
    COUNT(q.question_id) AS question_count
FROM assignments a
JOIN tutoring_groups tg ON a.group_id = tg.group_id
JOIN subjects s ON tg.subject_id = s.subject_id
LEFT JOIN assignment_questions q ON a.assignment_id = q.assignment_id
WHERE tg.teacher_id = :teacher_id
GROUP BY 
    a.assignment_id,
    a.title,
    a.description,
    a.due_date,
    a.created_at,
    a.assignment_type,
    a.worksheet_pdf_path,
    s.subject_name,
    tg.grade_level
ORDER BY a.assignment_id DESC
";

$stmtAssignments = $pdo->prepare($assignmentsQuery);
$stmtAssignments->execute([
    ':teacher_id' => $teacherId
]);

$assignments = $stmtAssignments->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>יצירת משימות AI למורה</title>

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
            padding: 40px 15px;
            display: block;
        }

        .app-container {
            width: 100%;
            max-width: 560px; 
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

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

        .hero p {
            font-size: 14px;
            color: #718096;
            margin-top: 8px;
            opacity: 0.85;
            line-height: 1.5;
        }

        .card {
            background: #fff9e6 !important;
            border-radius: 4px 4px 30px 4px / 4px 4px 10px 4px !important; 
            padding: 28px 22px !important;
            position: relative;
            border: 1px solid #f3ebd1 !important;
            width: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.01), 0 8px 20px rgba(80, 60, 40, 0.06);
            margin-bottom: 10px;
        }

        .card h2 {
            font-size: 18px;
            font-weight: 700;
            color: #333a42;
            margin-bottom: 15px;
            text-align: center;
        }

        label {
            display: block;
            margin-top: 14px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #5a6578;
            font-size: 13px;
        }

        select, input, textarea {
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

        textarea {
            min-height: 85px;
            resize: vertical;
            line-height: 1.6;
        }

        select:focus, input:focus, textarea:focus {
            border-color: #8fa3db;
            box-shadow: 0 0 0 3px rgba(143, 163, 219, 0.15);
        }

        .proposal-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 15px;
            width: 100%;
        }

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

        .questions-box {
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.6);
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 16px;
            display: none;
        }

        .questions-box h3 {
            font-size: 15px;
            color: #2d3748;
            margin-bottom: 12px;
            text-align: center;
        }

        .edit-note {
            background: #ebf8ff;
            color: #2b6cb0;
            border: 1px solid #bee3f8;
            border-radius: 12px;
            padding: 10px;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 14px;
            text-align: center;
        }

        .assignment-edit-header {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 16px;
        }

        .question {
            margin-bottom: 18px;
            padding: 14px;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            background: #ffffff;
        }

        .question-title {
            font-weight: 700;
            color: #2d3748;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .option-row {
            margin-top: 8px;
        }

        .correct-select-wrapper {
            margin-top: 12px;
            background: #f0fff4;
            border: 1px solid #c6f6d5;
            border-radius: 12px;
            padding: 10px;
        }

        .correct-select-wrapper label {
            margin-top: 0;
        }


        .formatted-question-text {
            margin-top: 10px;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.7;
            color: #2d3748;
            direction: rtl;
            white-space: normal;
        }

        .bagrut-results {
            display: none;
            margin-top: 18px;
            gap: 18px;
        }

        .bagrut-file-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
        }

        .bagrut-file-card h3 {
            margin-bottom: 10px;
            font-size: 15px;
        }

        .bagrut-preview {
            display: block;
            width: 100%;
            max-height: 540px;
            object-fit: contain;
            background: #ffffff;
            border: 1px solid #edf2f7;
            margin-bottom: 12px;
        }

        .bagrut-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .bagrut-actions a {
            flex: 1;
            min-width: 150px;
            text-align: center;
            text-decoration: none;
        }

        .question-preview-label {
            margin-top: 10px;
            font-size: 12px;
            font-weight: 700;
            color: #718096;
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

        .message {
            display: none;
            margin-top: 15px;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
            font-size: 14px;
            line-height: 1.6;
        }

        .info { background: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; }
        .error { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; }
        .success { background: #f0fff4; color: #2f855a; border: 1px solid #c6f6d5; }

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
        }

        .btn:hover {
            background: #7b8fc7;
            box-shadow: 0 6px 16px rgba(143, 163, 219, 0.5);
            transform: translateY(-1px);
        }

        .btn.secondary {
            background: #718096;
            box-shadow: 0 4px 12px rgba(113, 128, 150, 0.25);
        }

        .btn.secondary:hover {
            background: #5f6b7a;
            box-shadow: 0 6px 16px rgba(113, 128, 150, 0.35);
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
            font-size: 12px;
            font-family: 'Rubik', sans-serif !important;
        }

        th {
            background: #1e3a5f;
            color: white;
            font-weight: 500;
        }

        .empty {
            text-align: center;
            padding: 20px;
            color: #718096;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            body { padding: 20px 10px; }
            .card { padding: 20px 15px !important; }
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
        <a href="teacher_dashboard.php" class="back-btn">חזרה לסביבת מורה</a>
    </div>

    <div class="hero">
        <h1>משימות AI ודפי עבודה</h1>
        <p>שלום <?= htmlspecialchars($teacherName) ?>, כאן יוצרים דף עבודה אחד המותאם ליחידת הלימוד, לנושא ולתת־הנושא של הקבוצה.</p>
    </div>

    <?php if (count($groups) === 0): ?>
        <div class="card">
            <div class="empty">אין לך כרגע קבוצות תגבור משויכות ולכן לא ניתן ליצור משימות.</div>
        </div>
    <?php else: ?>

        <div class="card">
            <h2>יצירת דף עבודה לקבוצה</h2>
            <p style="font-size:13px; color:#718096; text-align:center; line-height:1.6; margin-bottom:12px;">
                תת־נושא יוצר דף עבודה מדורג. רק תת־נושא המסומן "שאלת סיכום" יוצר שאלה מלאה בסגנון בגרות.
            </p>

            <label>בחר קבוצת תגבור</label>
            <select id="groupId">
                <option value="">-- בחר קבוצה --</option>
                <?php foreach($groups as $group): ?>
                    <option
                        value="<?= $group['group_id'] ?>"
                        data-subject="<?= htmlspecialchars($group['subject_name']) ?>"
                        data-grade="<?= htmlspecialchars($group['grade_level']) ?>"
                        data-study-units="<?= htmlspecialchars((string)($group['study_units'] ?? '')) ?>"
                        <?= $prefillGroupId === (int)$group['group_id'] ? 'selected' : '' ?>
                    >
                        קבוצה <?= $group['group_id'] ?> | <?= htmlspecialchars($group['subject_name']) ?> | שכבה <?= htmlspecialchars($group['grade_level']) ?><?= !empty($group['study_units']) ? ' | ' . (int)$group['study_units'] . ' יח״ל' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div id="learningUnitInfo" style="display:none; margin-top:14px; padding:12px 14px; background:#f7fafc; border:1px solid #e2e8f0; border-radius:12px; font-size:13px;"></div>

            <div id="curriculumFields" style="display:none;">
                <label>נושא לבגרות</label>
                <select id="curriculumTopic">
                    <option value="">-- בחר נושא מרכזי --</option>
                </select>

                <label>תת־נושא בדרך לרמת בגרות</label>
                <select id="curriculumSubtopic" disabled>
                    <option value="">-- תחילה בחר נושא מרכזי --</option>
                </select>
                <p style="font-size:12px; color:#718096; margin-top:7px; line-height:1.5;">
                    הנושאים ותתי־הנושאים נגזרו מחוברת תוכנית הלימודים לי״א 4 יח״ל.
                </p>
                <p id="generationModeNote" style="display:none; font-size:12px; color:#1f4f70; margin-top:7px; line-height:1.5;"></p>
            </div>

            <input type="hidden" id="topic" value="<?= htmlspecialchars($prefillTopic) ?>">
            <p id="catalogUnavailableNote" style="display:none; font-size:12px; color:#b45309; margin-top:12px; line-height:1.5;">
                יצירת דפי עבודה זמינה כרגע לקבוצות מתמטיקה י״א 4 יח״ל. בהמשך יתווספו יחידות לימוד נוספות.
            </p>

            <label>תאריך הגשה אחרון</label>
            <input type="date" id="dueDate">

            <button class="btn" id="generateWorksheetButton" onclick="generateUnifiedWorksheet()">צור דף עבודה</button>
            <div id="message" class="message"></div>
            <div id="worksheetResult" class="bagrut-results"></div>
        </div>

    <?php endif; ?>

    <div class="card">
        <h2>משימות שכבר יצרת לקבוצות</h2>
        <?php if (count($assignments) === 0): ?>
            <div class="empty">עדיין לא יצרת משימות לקבוצות שלך.</div>
        <?php else: ?>
            <div style="overflow-x: auto; width: 100%;">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>מקצוע</th>
                        <th>שכבה</th>
                        <th>כותרת</th>
                        <th>סוג</th>
                        <th>תאריך הגשה</th>
                    </tr>
                    <?php foreach($assignments as $assignment): ?>
                        <tr>
                            <td><?= $assignment['assignment_id'] ?></td>
                            <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                            <td>שכבה <?= htmlspecialchars($assignment['grade_level']) ?></td>
                            <td><?= htmlspecialchars($assignment['title']) ?></td>
                            <td><strong><?= $assignment['assignment_type'] === 'worksheet_pdf' ? 'PDF' : htmlspecialchars($assignment['question_count']) ?></strong></td>
                            <td><?= htmlspecialchars($assignment['due_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
let generatedSuggestions = [];
let selectedAssignment = null;
let selectedAssignmentIndex = null;

const createdBy = <?= json_encode($_SESSION['teacher_username'] ?? 'teacher', JSON_UNESCAPED_UNICODE) ?>;
const curriculumCatalog = <?= json_encode($curriculumCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function getSelectedGroupCatalog() {
    const groupSelect = document.getElementById('groupId');
    if (!groupSelect) return null;
    const option = groupSelect.options[groupSelect.selectedIndex];

    if (!option || !option.value) {
        return null;
    }

    const subject = String(option.dataset.subject || '').trim().toLowerCase();
    const isMath = ['math', 'mathematics', 'מתמטיקה'].includes(subject);
    const key = isMath
        ? `math-${Number(option.dataset.grade)}-${Number(option.dataset.studyUnits)}`
        : '';

    return curriculumCatalog[key] || null;
}

function updateTopicValue() {
    const catalog = getSelectedGroupCatalog();
    const topicSelect = document.getElementById('curriculumTopic');
    const subtopicSelect = document.getElementById('curriculumSubtopic');
    const topicInput = document.getElementById('topic');

    if (!catalog || !topicSelect.value || !subtopicSelect.value) {
        if (catalog) topicInput.value = '';
        document.getElementById('generationModeNote').style.display = 'none';
        return;
    }

    topicInput.value = `${catalog.topics[topicSelect.value].name} — ${catalog.topics[topicSelect.value].subtopics[subtopicSelect.value]}`;
    const isSummary = subtopicSelect.value.endsWith('-bagrut');
    const hours = Number(catalog.topics[topicSelect.value].subtopic_hours?.[subtopicSelect.value] || 0);
    const note = document.getElementById('generationModeNote');
    note.textContent = isSummary
        ? `תוצר: שאלת סיכום מלאה בסגנון בגרות${hours ? ` | הקצאה בתוכנית: ${hours} שעות` : ''}`
        : `תוצר: דף עבודה מדורג — בסיס, תרגול, מתקדם ויישום${hours ? ` | הקצאה בתוכנית: ${hours} שעות` : ''}`;
    note.style.display = 'block';
    document.getElementById('generateWorksheetButton').textContent = isSummary
        ? 'צור שאלת סיכום מלאה'
        : 'צור דף עבודה מדורג';
}

function populateSubtopics() {
    const catalog = getSelectedGroupCatalog();
    const topicSelect = document.getElementById('curriculumTopic');
    const subtopicSelect = document.getElementById('curriculumSubtopic');
    const topic = catalog?.topics?.[topicSelect.value];

    subtopicSelect.innerHTML = '<option value="">-- בחר תת־נושא --</option>';
    subtopicSelect.disabled = !topic;

    if (topic) {
        Object.entries(topic.subtopics).forEach(([id, name]) => {
            const hours = Number(topic.subtopic_hours?.[id] || 0);
            const type = id.endsWith('-bagrut') ? ' | שאלת סיכום' : ' | דף עבודה';
            subtopicSelect.add(new Option(`${name}${hours ? ` (${hours} שעות)` : ''}${type}`, id));
        });
    }

    updateTopicValue();
}

function updateCurriculumFields() {
    const groupSelect = document.getElementById('groupId');
    if (!groupSelect) return;
    const hasSelectedGroup = Boolean(groupSelect.value);
    const catalog = getSelectedGroupCatalog();
    const curriculumFields = document.getElementById('curriculumFields');
    const note = document.getElementById('catalogUnavailableNote');
    const topicSelect = document.getElementById('curriculumTopic');
    const subtopicSelect = document.getElementById('curriculumSubtopic');
    const generateButton = document.getElementById('generateWorksheetButton');
    const unitInfo = document.getElementById('learningUnitInfo');
    const selectedOption = groupSelect.options[groupSelect.selectedIndex];

    curriculumFields.style.display = catalog ? 'block' : 'none';
    note.style.display = !catalog && hasSelectedGroup ? 'block' : 'none';
    generateButton.disabled = !catalog;
    unitInfo.style.display = hasSelectedGroup ? 'block' : 'none';
    unitInfo.innerHTML = hasSelectedGroup
        ? `<strong>יחידת לימוד:</strong> ${Number(selectedOption.dataset.studyUnits) || 'לא הוגדרה'} יח״ל | <strong>שכבה:</strong> ${escapeHtml(selectedOption.dataset.grade || '')}`
        : '';
    document.getElementById('worksheetResult').innerHTML = '';
    document.getElementById('worksheetResult').style.display = 'none';
    document.getElementById('generationModeNote').style.display = 'none';
    generateButton.textContent = 'צור דף עבודה';

    topicSelect.innerHTML = '<option value="">-- בחר נושא מרכזי --</option>';
    subtopicSelect.innerHTML = '<option value="">-- תחילה בחר נושא מרכזי --</option>';
    subtopicSelect.disabled = true;

    if (catalog) {
        document.getElementById('topic').value = '';
        Object.entries(catalog.topics).forEach(([id, topic]) => {
            const hours = topic.hours ? ` (${topic.hours} שעות בתוכנית)` : '';
            topicSelect.add(new Option(topic.name + hours, id));
        });
    }
}

if (document.getElementById('groupId')) {
    document.getElementById('groupId').addEventListener('change', updateCurriculumFields);
    document.getElementById('curriculumTopic').addEventListener('change', populateSubtopics);
    document.getElementById('curriculumSubtopic').addEventListener('change', updateTopicValue);
    updateCurriculumFields();
}

let generatedWorksheet = null;

async function generateUnifiedWorksheet(){
    const groupId = document.getElementById('groupId').value;
    const topicId = document.getElementById('curriculumTopic').value;
    const subtopicId = document.getElementById('curriculumSubtopic').value;
    const dueDate = document.getElementById('dueDate').value;
    const results = document.getElementById('worksheetResult');

    if (!groupId) {
        alert('יש לבחור קבוצת תגבור.');
        return;
    }
    if (!getSelectedGroupCatalog()) {
        alert('יצירת דף עבודה זמינה כרגע למתמטיקה י״א 4 יח״ל.');
        return;
    }
    if (!topicId || !subtopicId) {
        alert('יש לבחור נושא ותת־נושא.');
        return;
    }
    if (!dueDate) {
        alert('יש לבחור תאריך הגשה.');
        return;
    }

    generatedWorksheet = null;
    results.innerHTML = '';
    results.style.display = 'none';
    showMessage('יוצר דף עבודה אחד ובודק את המבנה המתמטי ואת קובץ ה־PDF...', 'info');

    try {
        const response = await fetch('api/generate_worksheet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                group_id: groupId,
                curriculum_topic_id: topicId,
                curriculum_subtopic_id: subtopicId
            })
        });
        const raw = await response.text();
        let payload;
        try {
            payload = JSON.parse(raw);
        } catch (error) {
            throw new Error('השרת לא החזיר תשובה תקינה.');
        }
        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'יצירת דף העבודה נכשלה.');
        }

        const file = payload.file || payload.files?.[0];
        if (!file) throw new Error('לא התקבל קובץ דף עבודה.');
        generatedWorksheet = {
            ...file,
            curriculum_selection: payload.curriculum_selection,
            group_id: groupId,
            due_date: dueDate
        };

        results.innerHTML = `
            <div class="bagrut-file-card">
                <h3>${escapeHtml(file.title || 'דף עבודה')}</h3>
                <img class="bagrut-preview" src="${escapeHtml(file.preview_url)}" alt="תצוגה מקדימה של דף העבודה">
                <div class="bagrut-actions">
                    <a class="btn secondary" href="${escapeHtml(file.pdf_url)}" target="_blank" rel="noopener">פתח PDF</a>
                    <a class="btn" href="${escapeHtml(file.pdf_url)}" download>הורד PDF</a>
                </div>
                <button class="btn" type="button" onclick="saveGeneratedWorksheet()">אשר ושייך את דף העבודה לקבוצה</button>
            </div>
        `;
        results.style.display = 'grid';
        showMessage('דף עבודה אחד נוצר בהצלחה. בדוק אותו ולאחר מכן אשר את השיוך לקבוצה.', 'success');
        results.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
        showMessage(error.message || 'אירעה שגיאה ביצירת דף העבודה.', 'error');
    }
}

async function saveGeneratedWorksheet(){
    if (!generatedWorksheet) return;
    try {
        const response = await fetch('api/save_generated_worksheet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(generatedWorksheet)
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'שמירת דף העבודה נכשלה.');
        }
        showMessage('דף העבודה נשמר ושויך לקבוצה בהצלחה.', 'success');
        setTimeout(() => location.reload(), 900);
    } catch (error) {
        showMessage(error.message || 'אירעה שגיאה בשמירת דף העבודה.', 'error');
    }
}

function showMessage(text, type){
    const msg = document.getElementById('message');
    msg.className = 'message ' + type;
    msg.innerText = text;
    msg.style.display = 'block';
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

function updateQuestionPreview(index) {
    const textarea = document.getElementById(`q_${index}_text`);
    const preview = document.getElementById(`q_${index}_preview`);

    if (!textarea || !preview) {
        return;
    }

    preview.innerHTML = formatTextWithCode(textarea.value);
}


function cleanAiErrorMessage(message) {
    message = String(message || '').trim();

    const looksLikeInternalPrompt =
        message.includes('הנחיות מקצועיות') ||
        message.includes('דרישות איכות') ||
        message.includes('מבנה המשימה') ||
        message.includes('בדיקת איכות פנימית') ||
        message.includes('```text') ||
        message.length > 350;

    if (looksLikeInternalPrompt) {
        return 'הנושא הנ"ל לא חלק מתוכנית הלימוד אנא הקלד נושא רלוונטי.';
    }

    return message || 'שגיאה ביצירת הצעות.';
}

function normalizeOption(option) {
    option = String(option || '').toLowerCase().trim();

    if (['a', 'b', 'c', 'd'].includes(option)) {
        return option;
    }

    if (option === 'א') return 'a';
    if (option === 'ב') return 'b';
    if (option === 'ג') return 'c';
    if (option === 'ד') return 'd';

    return 'a';
}

async function generateSuggestions(){
    const groupId = document.getElementById('groupId').value;
    const topic = document.getElementById('topic').value.trim();

    if(!groupId){ alert('יש לבחור קבוצת תגבור'); return; }
    if(!topic){ alert(getSelectedGroupCatalog() ? 'יש לבחור נושא ותת־נושא' : 'יש להזין נושא שיעור'); return; }

    generatedSuggestions = [];
    selectedAssignment = null;
    selectedAssignmentIndex = null;

    document.getElementById('proposalGrid').innerHTML = '';
    document.getElementById('questionsBox').style.display = 'none';
    document.getElementById('suggestionsCard').style.display = 'none';

    showMessage('יוצר הצעות AI, נא להמתין... ⏳', 'info');

    try {
        const response = await fetch('api/generate_assignment_suggestions.php', {
            method:'POST',
            headers:{ 'Content-Type':'application/json' },
            body:JSON.stringify({
                group_id: groupId,
                topic: topic,
                curriculum_topic_id: document.getElementById('curriculumTopic').value,
                curriculum_subtopic_id: document.getElementById('curriculumSubtopic').value
            })
        });

        const rawText = await response.text();

        let result = null;

        try {
            result = JSON.parse(rawText);
        } catch(parseError) {
            console.error('Raw server response:', rawText);
            showMessage(
                'השרת לא החזיר JSON תקין. סטטוס: ' + response.status +
                '<br>תחילת תשובת השרת:<br><pre style="direction:ltr;text-align:left;white-space:pre-wrap;background:#fff;padding:10px;border-radius:8px;margin-top:8px;">' +
                escapeHtml(rawText.substring(0, 700)) +
                '</pre>',
                'error'
            );
            return;
        }

        if(!result.success){
            showMessage('שגיאה ביצירת הצעות: ' + cleanAiErrorMessage(result.message), 'error');
            return;
        }

        generatedSuggestions = result.suggestions;
        renderSuggestions();

        showMessage('הצעות AI נוצרו בהצלחה ✅ עכשיו אפשר לבחור הצעה ולערוך אותה לפני שליחה.', 'success');
        document.getElementById('suggestionsCard').style.display = 'block';
        document.getElementById('suggestionsCard').scrollIntoView({ behavior:'smooth' });

    } catch(error) {
        showMessage('שגיאה בקריאה לשרת או ל־AI. פתח Console/Network או שלח צילום של ההודעה המפורטת שמופיעה מעל.', 'error');
    }
}

function renderSuggestions(){
    const proposalGrid = document.getElementById('proposalGrid');
    let html = '';

    generatedSuggestions.forEach((assignment, index) => {
        const questionCount = assignment.questions && Array.isArray(assignment.questions)
            ? assignment.questions.length
            : 0;

        html += `
            <div class="proposal-card" id="proposal_${index}" onclick="selectAssignment(${index})">
                <h3>${escapeHtml(assignment.title)}</h3>
                <p>${escapeHtml(assignment.description)}</p>
                <p style="margin-top:4px; font-size:12px; color:#8fa3db;">
                    <strong>${questionCount}</strong> שאלות אמריקאיות לעריכה
                </p>
            </div>
        `;
    });

    proposalGrid.innerHTML = html;
}

function selectAssignment(index){
    selectedAssignmentIndex = index;

    /*
        יוצרים עותק עמוק כדי שהמורה יוכל לערוך בלי לפגוע בהצעה המקורית.
    */
    selectedAssignment = JSON.parse(JSON.stringify(generatedSuggestions[index]));

    document.querySelectorAll('.proposal-card').forEach(card => card.classList.remove('selected'));
    document.getElementById('proposal_' + index).classList.add('selected');

    renderEditableQuestions();
}

function renderEditableQuestions(){
    if(!selectedAssignment){ return; }

    const questionsBox = document.getElementById('questionsBox');

    let html = `
        <h3>עריכת המשימה לפני שליחה</h3>

        <div class="edit-note">
            ה־AI יצר הצעה ראשונית. כאן המורה יכול לתקן ניסוח, להחליף תשובה נכונה,
            לערוך תשובות שגויות ולהרחיב את ההסבר לפני שהתלמידים מקבלים את המשימה.
        </div>

        <div class="assignment-edit-header">
            <label>כותרת המשימה</label>
            <input type="text" id="editTitle" value="${escapeHtml(selectedAssignment.title || '')}">

            <label>תיאור המשימה</label>
            <textarea id="editDescription">${escapeHtml(selectedAssignment.description || '')}</textarea>
        </div>
    `;

    selectedAssignment.questions.forEach((q, index) => {
        const correctOption = normalizeOption(q.correct_option);

        html += `
            <div class="question" data-question-index="${index}">
                <div class="question-title">שאלה ${index + 1}</div>

                <label>נוסח השאלה</label>
                <textarea id="q_${index}_text" oninput="updateQuestionPreview(${index})">${escapeHtml(q.question_text || '')}</textarea>

                <div class="question-preview-label">תצוגה מקדימה לתלמיד</div>
                <div class="formatted-question-text" id="q_${index}_preview">${formatTextWithCode(q.question_text || '')}</div>

                <div class="option-row">
                    <label>תשובה א׳</label>
                    <input type="text" id="q_${index}_a" value="${escapeHtml(q.option_a || '')}">
                </div>

                <div class="option-row">
                    <label>תשובה ב׳</label>
                    <input type="text" id="q_${index}_b" value="${escapeHtml(q.option_b || '')}">
                </div>

                <div class="option-row">
                    <label>תשובה ג׳</label>
                    <input type="text" id="q_${index}_c" value="${escapeHtml(q.option_c || '')}">
                </div>

                <div class="option-row">
                    <label>תשובה ד׳</label>
                    <input type="text" id="q_${index}_d" value="${escapeHtml(q.option_d || '')}">
                </div>

                <div class="correct-select-wrapper">
                    <label>התשובה הנכונה</label>
                    <select id="q_${index}_correct">
                        <option value="a" ${correctOption === 'a' ? 'selected' : ''}>א׳</option>
                        <option value="b" ${correctOption === 'b' ? 'selected' : ''}>ב׳</option>
                        <option value="c" ${correctOption === 'c' ? 'selected' : ''}>ג׳</option>
                        <option value="d" ${correctOption === 'd' ? 'selected' : ''}>ד׳</option>
                    </select>
                </div>

                <label>הסבר לתשובה</label>
                <textarea id="q_${index}_explanation">${escapeHtml(q.explanation || '')}</textarea>
            </div>
        `;
    });

    html += `
        <button class="btn secondary" type="button" onclick="updateEditedAssignmentFromForm(true)">
            עדכן תצוגה/בדוק עריכות
        </button>
    `;

    questionsBox.innerHTML = html;
    questionsBox.style.display = 'block';

    questionsBox.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

function updateEditedAssignmentFromForm(showSuccessMessage = false) {
    if (!selectedAssignment) {
        return false;
    }

    const title = document.getElementById('editTitle')?.value.trim() || '';
    const description = document.getElementById('editDescription')?.value.trim() || '';

    if (!title) {
        alert('יש להזין כותרת למשימה.');
        return false;
    }

    if (!description) {
        alert('יש להזין תיאור למשימה.');
        return false;
    }

    const editedQuestions = [];

    for (let i = 0; i < selectedAssignment.questions.length; i++) {
        const questionText = document.getElementById(`q_${i}_text`)?.value.trim() || '';
        const optionA = document.getElementById(`q_${i}_a`)?.value.trim() || '';
        const optionB = document.getElementById(`q_${i}_b`)?.value.trim() || '';
        const optionC = document.getElementById(`q_${i}_c`)?.value.trim() || '';
        const optionD = document.getElementById(`q_${i}_d`)?.value.trim() || '';
        const correctOption = document.getElementById(`q_${i}_correct`)?.value || '';
        const explanation = document.getElementById(`q_${i}_explanation`)?.value.trim() || '';

        if (!questionText || !optionA || !optionB || !optionC || !optionD || !correctOption || !explanation) {
            alert(`יש להשלים את כל השדות בשאלה ${i + 1}.`);
            return false;
        }

        const optionsForDuplicateCheck = [
            optionA.toLowerCase(),
            optionB.toLowerCase(),
            optionC.toLowerCase(),
            optionD.toLowerCase()
        ];

        const uniqueOptions = new Set(optionsForDuplicateCheck);

        if (uniqueOptions.size < 4) {
            alert(`בשאלה ${i + 1} יש שתי תשובות זהות או יותר. צריך ארבע אפשרויות שונות.`);
            return false;
        }

        editedQuestions.push({
            question_text: questionText,
            option_a: optionA,
            option_b: optionB,
            option_c: optionC,
            option_d: optionD,
            correct_option: correctOption,
            explanation: explanation
        });
    }

    selectedAssignment.title = title;
    selectedAssignment.description = description;
    selectedAssignment.questions = editedQuestions;

    if (showSuccessMessage) {
        alert('העריכות עודכנו בהצלחה ✅ עכשיו אפשר לשמור ולשלוח לקבוצה.');
    }

    return true;
}

async function saveSelectedAssignment(){
    const groupId = document.getElementById('groupId').value;
    const dueDate = document.getElementById('dueDate').value;

    if(!groupId){
        alert('יש לבחור קבוצת תגבור');
        return;
    }

    if(!selectedAssignment){
        alert('יש לבחור אחת מהצעות ה־AI');
        return;
    }

    if(!dueDate){
        alert('יש לבחור תאריך הגשה');
        return;
    }

    const formValid = updateEditedAssignmentFromForm(false);

    if (!formValid) {
        return;
    }

    const confirmSave = confirm('האם לשמור ולשלוח לתלמידים את הגרסה הערוכה של המשימה?');

    if (!confirmSave) {
        return;
    }

    try {
        const response = await fetch('api/save_assignment.php', {
            method:'POST',
            headers:{ 'Content-Type':'application/json' },
            body:JSON.stringify({
                group_id: groupId,
                title: selectedAssignment.title,
                description: selectedAssignment.description,
                due_date: dueDate,
                created_by: createdBy,
                questions: selectedAssignment.questions
            })
        });

        const result = await response.json();

        if(result.success){
            const parentAssignmentEmails = result.mail_results?.parent_assignment_emails_sent ?? 0;
            const failedEmailsCount = result.mail_results?.failed_emails?.length ?? 0;

            let message = 'המשימה הערוכה נשמרה בהצלחה ונשלחה לקבוצה ✅';
            message += '\n\nמיילים להורים שנשלחו: ' + parentAssignmentEmails;

            if (failedEmailsCount > 0) {
                message += '\nשימו לב: היו ' + failedEmailsCount + ' מיילים שלא נשלחו.';
            }

            alert(message);
            location.reload();
        } else {
            alert('שגיאה: ' + result.message);
        }

    } catch (error) {
        alert('שגיאה בתקשורת עם השרת בזמן שמירת המשימה.');
    }
}
</script>

</body>
</html>
