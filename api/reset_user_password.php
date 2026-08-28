<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once "../db_config.php";

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'coordinator') {
    echo json_encode([
        "success" => false,
        "message" => "אין הרשאה לבצע פעולה זו"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "לא התקבלו נתונים"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = !empty($data['user_id']) ? (int)$data['user_id'] : 0;
$newPassword = trim($data['new_password'] ?? '');

if (!$userId || $newPassword === '') {
    echo json_encode([
        "success" => false,
        "message" => "יש לבחור משתמש ולהזין סיסמה חדשה"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode([
        "success" => false,
        "message" => "הסיסמה חייבת להכיל לפחות 8 תווים"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

try {

    $stmt = $pdo->prepare("
        UPDATE users
        SET password = :password
        WHERE user_id = :user_id
    ");

    $stmt->execute([
        ':password' => $newPasswordHash,
        ':user_id' => $userId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "הסיסמה אופסה בהצלחה"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
