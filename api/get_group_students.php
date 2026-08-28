<?php
header('Content-Type: application/json; charset=utf-8');

require_once "../db_config.php";

if (!isset($_GET['group_id']) || $_GET['group_id'] === '') {
    echo json_encode([]);
    exit;
}

$groupId = (int)$_GET['group_id'];

$sql = "
SELECT 
    s.student_id,
    s.first_name,
    s.last_name,
    s.grade_level,
    s.class_name,
    s.email
FROM tutoring_group_students tgs
JOIN students s ON tgs.student_id = s.student_id
WHERE tgs.group_id = :group_id
ORDER BY s.last_name, s.first_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':group_id' => $groupId
]);

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($students, JSON_UNESCAPED_UNICODE);