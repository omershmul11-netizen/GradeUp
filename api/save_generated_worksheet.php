<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../bagrut471/curriculum_catalog.php';

function worksheet_save_exit(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['teacher_id'])) {
    worksheet_save_exit(['success' => false, 'message' => 'יש להתחבר כמורה.'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    worksheet_save_exit(['success' => false, 'message' => 'לא התקבלו נתונים תקינים.'], 400);
}

$groupId = (int)($data['group_id'] ?? 0);
$dueDate = trim((string)($data['due_date'] ?? ''));
$pdfUrl = trim((string)($data['pdf_url'] ?? ''));
$previewUrl = trim((string)($data['preview_url'] ?? ''));
$topicId = trim((string)($data['curriculum_selection']['topic_id'] ?? ''));
$subtopicId = trim((string)($data['curriculum_selection']['subtopic_id'] ?? ''));

$date = DateTime::createFromFormat('Y-m-d', $dueDate);
if (!$date || $date->format('Y-m-d') !== $dueDate) {
    worksheet_save_exit(['success' => false, 'message' => 'תאריך ההגשה אינו תקין.'], 422);
}

$groupStmt = $pdo->prepare(
    'SELECT tg.group_id, tg.grade_level, tg.study_units, s.subject_name
     FROM tutoring_groups tg
     JOIN subjects s ON s.subject_id = tg.subject_id
     WHERE tg.group_id = :group_id AND tg.teacher_id = :teacher_id AND tg.status = \'approved\'
     LIMIT 1'
);
$groupStmt->execute([
    ':group_id' => $groupId,
    ':teacher_id' => (int)$_SESSION['teacher_id'],
]);
$group = $groupStmt->fetch(PDO::FETCH_ASSOC);
if (!$group) {
    worksheet_save_exit(['success' => false, 'message' => 'הקבוצה אינה משויכת למורה המחובר.'], 403);
}

$catalog = gradeup_curriculum_for_group(
    (string)$group['subject_name'],
    (int)$group['grade_level'],
    isset($group['study_units']) ? (int)$group['study_units'] : null
);
$selection = $catalog ? gradeup_curriculum_selection($catalog, $topicId, $subtopicId) : null;
if (!$selection) {
    worksheet_save_exit(['success' => false, 'message' => 'הנושא שנבחר אינו תואם ליחידת הלימוד של הקבוצה.'], 422);
}

$allowedPrefix = 'generated/bagrut-471/';
if (!str_starts_with($pdfUrl, $allowedPrefix) || !str_ends_with($pdfUrl, '.pdf')) {
    worksheet_save_exit(['success' => false, 'message' => 'נתיב קובץ דף העבודה אינו תקין.'], 422);
}
if ($previewUrl !== '' && !str_starts_with($previewUrl, $allowedPrefix)) {
    worksheet_save_exit(['success' => false, 'message' => 'נתיב התצוגה המקדימה אינו תקין.'], 422);
}

$relativePdf = rawurldecode($pdfUrl);
$absolutePdf = realpath(__DIR__ . '/../' . $relativePdf);
$generatedRoot = realpath(__DIR__ . '/../generated/bagrut-471');
if (!$absolutePdf || !$generatedRoot || !str_starts_with($absolutePdf, $generatedRoot . DIRECTORY_SEPARATOR)) {
    worksheet_save_exit(['success' => false, 'message' => 'קובץ דף העבודה לא נמצא.'], 422);
}

$titlePrefix = $selection['generation_mode'] === 'summary_question' ? 'שאלת סיכום — ' : 'דף עבודה — ';
$title = $titlePrefix . $selection['subtopic_name'];
$description = $selection['topic_name'] . ' | ' . $selection['subtopic_name'] . ' | ' . $selection['generation_label'] . ' | י״א 4 יח״ל';
$stmt = $pdo->prepare(
    'INSERT INTO assignments
     (group_id, title, description, due_date, created_by, assignment_type,
      worksheet_pdf_path, worksheet_preview_path, curriculum_topic_id, curriculum_subtopic_id)
     VALUES
     (:group_id, :title, :description, :due_date, :created_by, \'worksheet_pdf\',
      :pdf_path, :preview_path, :topic_id, :subtopic_id)'
);
$stmt->execute([
    ':group_id' => $groupId,
    ':title' => $title,
    ':description' => $description,
    ':due_date' => $dueDate,
    ':created_by' => (string)($_SESSION['teacher_username'] ?? 'teacher'),
    ':pdf_path' => $pdfUrl,
    ':preview_path' => $previewUrl,
    ':topic_id' => $topicId,
    ':subtopic_id' => $subtopicId,
]);

worksheet_save_exit([
    'success' => true,
    'assignment_id' => (int)$pdo->lastInsertId(),
    'message' => 'דף העבודה נשמר ושויך לקבוצה.',
]);
