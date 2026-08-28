<?php
header('Content-Type: application/json; charset=utf-8');
require_once "db.php";

$grade = isset($_GET['grade']) ? $_GET['grade'] : '';
$subject = isset($_GET['subject']) ? $_GET['subject'] : '';

if ($grade === '' || $subject === '') {
    echo json_encode([
        "success" => false,
        "message" => "Missing grade or subject"
    ]);
    exit;
}

$sql = "
    SELECT 
        s.student_id,
        s.first_name,
        s.last_name,
        s.grade_level,
        s.class_name,
        s.email,
        sub.subject_name,
        g.latest_grade
    FROM students s
    JOIN student_subject_grades g ON s.student_id = g.student_id
    JOIN subjects sub ON g.subject_id = sub.subject_id
    WHERE s.grade_level = ?
      AND sub.subject_name = ?
      AND g.latest_grade BETWEEN 60 AND 70
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $grade, $subject);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode([
    "success" => true,
    "count" => count($students),
    "students" => $students
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>