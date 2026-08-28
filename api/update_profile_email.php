<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once "../db_config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['email'])) {
    echo json_encode([
        "success" => false,
        "message" => "לא התקבל מייל לעדכון"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$email = trim($data['email']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "כתובת המייל אינה תקינה"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {

    /*
        תלמיד מחובר ישירות דרך student_id.
    */
    if (isset($_SESSION['student_id'])) {

        $studentId = (int)$_SESSION['student_id'];

        $stmt = $pdo->prepare("
            UPDATE students
            SET email = :email
            WHERE student_id = :student_id
        ");

        $stmt->execute([
            ':email' => $email,
            ':student_id' => $studentId
        ]);

        $_SESSION['student_email'] = $email;

        echo json_encode([
            "success" => true,
            "message" => "המייל עודכן בהצלחה"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
        מורה מחובר ישירות דרך teacher_id.
    */
    if (isset($_SESSION['teacher_id'])) {

        $teacherId = (int)$_SESSION['teacher_id'];

        $stmt = $pdo->prepare("
            UPDATE teachers
            SET email = :email
            WHERE teacher_id = :teacher_id
        ");

        $stmt->execute([
            ':email' => $email,
            ':teacher_id' => $teacherId
        ]);

        $_SESSION['teacher_email'] = $email;

        echo json_encode([
            "success" => true,
            "message" => "המייל עודכן בהצלחה"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
        משתמש שמזוהה לפי username:
        יכול להיות teacher / student / coordinator.
    */
    if (isset($_SESSION['user'])) {

        $username = $_SESSION['user'];

        $userStmt = $pdo->prepare("
            SELECT 
                user_id,
                role,
                teacher_id,
                student_id,
                coordinator_id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $userStmt->execute([$username]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode([
                "success" => false,
                "message" => "המשתמש לא נמצא"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        /*
            עדכון מייל מורה.
        */
        if ($user['role'] === 'teacher' && !empty($user['teacher_id'])) {

            $stmt = $pdo->prepare("
                UPDATE teachers
                SET email = :email
                WHERE teacher_id = :teacher_id
            ");

            $stmt->execute([
                ':email' => $email,
                ':teacher_id' => $user['teacher_id']
            ]);

            $_SESSION['teacher_email'] = $email;

            echo json_encode([
                "success" => true,
                "message" => "המייל עודכן בהצלחה"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        /*
            עדכון מייל תלמיד.
        */
        if ($user['role'] === 'student' && !empty($user['student_id'])) {

            $stmt = $pdo->prepare("
                UPDATE students
                SET email = :email
                WHERE student_id = :student_id
            ");

            $stmt->execute([
                ':email' => $email,
                ':student_id' => $user['student_id']
            ]);

            $_SESSION['student_email'] = $email;

            echo json_encode([
                "success" => true,
                "message" => "המייל עודכן בהצלחה"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        /*
            עדכון מייל הנהלה / רכז.
            עובד מול טבלת coordinators.
        */
        if ($user['role'] === 'coordinator' && !empty($user['coordinator_id'])) {

            $stmt = $pdo->prepare("
                UPDATE coordinators
                SET email = :email
                WHERE coordinator_id = :coordinator_id
            ");

            $stmt->execute([
                ':email' => $email,
                ':coordinator_id' => $user['coordinator_id']
            ]);

            $_SESSION['coordinator_email'] = $email;

            echo json_encode([
                "success" => true,
                "message" => "המייל עודכן בהצלחה"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            "success" => false,
            "message" => "המשתמש אינו מקושר לרשומת פרופיל מתאימה"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        "success" => false,
        "message" => "לא נמצא משתמש מחובר"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>