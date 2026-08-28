<?php
header('Content-Type: application/json; charset=utf-8');

require_once "../db_config.php";

if (!isset($_GET['assignment_id']) || $_GET['assignment_id'] === '') {
    echo json_encode([
        "success" => false,
        "message" => "חסר מזהה משימה"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$assignmentId = (int)$_GET['assignment_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            question_id,
            question_text,
            option_a,
            option_b,
            option_c,
            option_d
        FROM assignment_questions
        WHERE assignment_id = :assignment_id
        ORDER BY question_id ASC
    ");

    $stmt->execute([
        ':assignment_id' => $assignmentId
    ]);

    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "questions" => $questions
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}