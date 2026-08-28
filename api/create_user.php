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

$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');
$role = trim($data['role'] ?? '');

$studentId = !empty($data['student_id']) ? (int)$data['student_id'] : null;
$teacherId = !empty($data['teacher_id']) ? (int)$data['teacher_id'] : null;
$coordinatorId = !empty($data['coordinator_id']) ? (int)$data['coordinator_id'] : null;

$allowedRoles = ['coordinator', 'teacher', 'student'];

if ($username === '' || $password === '' || $role === '') {
    echo json_encode([
        "success" => false,
        "message" => "יש למלא שם משתמש, סיסמה ותפקיד"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode([
        "success" => false,
        "message" => "הסיסמה חייבת להכיל לפחות 8 תווים"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

if (!in_array($role, $allowedRoles, true)) {
    echo json_encode([
        "success" => false,
        "message" => "תפקיד לא תקין"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($role === 'student') {
    if (!$studentId) {
        echo json_encode([
            "success" => false,
            "message" => "משתמש תלמיד חייב להיות מקושר לתלמיד"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $teacherId = null;
    $coordinatorId = null;
}

if ($role === 'teacher') {
    if (!$teacherId) {
        echo json_encode([
            "success" => false,
            "message" => "משתמש מורה חייב להיות מקושר למורה"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $studentId = null;
    $coordinatorId = null;
}

if ($role === 'coordinator') {
    if (!$coordinatorId) {
        echo json_encode([
            "success" => false,
            "message" => "משתמש הנהלה חייב להיות מקושר לאיש הנהלה"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $studentId = null;
    $teacherId = null;
}

try {

    /*
        בדיקה ששם המשתמש לא קיים.
    */
    $checkStmt = $pdo->prepare("
        SELECT user_id 
        FROM users 
        WHERE username = ?
        LIMIT 1
    ");

    $checkStmt->execute([$username]);
    $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        echo json_encode([
            "success" => false,
            "message" => "שם המשתמש כבר קיים במערכת"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
        בדיקה שהתלמיד / מורה / הנהלה באמת קיימים.
    */
    if ($role === 'student') {
        $stmtCheckStudent = $pdo->prepare("
            SELECT student_id
            FROM students
            WHERE student_id = ?
            LIMIT 1
        ");
        $stmtCheckStudent->execute([$studentId]);

        if (!$stmtCheckStudent->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                "success" => false,
                "message" => "התלמיד שנבחר לא נמצא במערכת"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $linkedUserStmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE student_id = ?
              AND role = 'student'
            LIMIT 1
        ");
        $linkedUserStmt->execute([$studentId]);

        if ($linkedUserStmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                "success" => false,
                "message" => "לתלמיד הזה כבר קיים משתמש במערכת"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($role === 'teacher') {
        $stmtCheckTeacher = $pdo->prepare("
            SELECT teacher_id
            FROM teachers
            WHERE teacher_id = ?
            LIMIT 1
        ");
        $stmtCheckTeacher->execute([$teacherId]);

        if (!$stmtCheckTeacher->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                "success" => false,
                "message" => "המורה שנבחר לא נמצא במערכת"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $linkedUserStmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE teacher_id = ?
              AND role = 'teacher'
            LIMIT 1
        ");
        $linkedUserStmt->execute([$teacherId]);

        if ($linkedUserStmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                "success" => false,
                "message" => "למורה הזה כבר קיים משתמש במערכת"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($role === 'coordinator') {
        $stmtCheckCoordinator = $pdo->prepare("
            SELECT coordinator_id
            FROM coordinators
            WHERE coordinator_id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmtCheckCoordinator->execute([$coordinatorId]);

        if (!$stmtCheckCoordinator->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                "success" => false,
                "message" => "איש ההנהלה שנבחר לא נמצא או אינו פעיל"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $linkedUserStmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE coordinator_id = ?
              AND role = 'coordinator'
            LIMIT 1
        ");
        $linkedUserStmt->execute([$coordinatorId]);

        if ($linkedUserStmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                "success" => false,
                "message" => "לאיש ההנהלה הזה כבר קיים משתמש במערכת"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /*
        יצירת המשתמש.
        שים לב: חובה שבטבלת users קיימת העמודה coordinator_id.
    */
    $stmt = $pdo->prepare("
        INSERT INTO users
        (username, password, role, student_id, teacher_id, coordinator_id)
        VALUES
        (:username, :password, :role, :student_id, :teacher_id, :coordinator_id)
    ");

    $stmt->execute([
        ':username' => $username,
        ':password' => $passwordHash,
        ':role' => $role,
        ':student_id' => $studentId,
        ':teacher_id' => $teacherId,
        ':coordinator_id' => $coordinatorId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "המשתמש נוצר בהצלחה"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
