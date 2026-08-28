<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once "../db_config.php";
require_once "../mail_config.php";

if (!isset($_SESSION['teacher_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "אין הרשאה. יש להתחבר כמורה."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$teacherId = (int)$_SESSION['teacher_id'];

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "לא התקבלו נתונים"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    empty($data['group_id']) ||
    empty($data['title']) ||
    empty($data['questions']) ||
    !is_array($data['questions'])
) {
    echo json_encode([
        "success" => false,
        "message" => "חסרים נתונים: קבוצה, כותרת או שאלות"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$groupId = (int)$data['group_id'];
$title = trim($data['title']);
$description = !empty($data['description'])
    ? trim($data['description'])
    : 'משימה אמריקאית שנוצרה על ידי המערכת';

$dueDate = !empty($data['due_date']) ? trim($data['due_date']) : null;
$createdBy = !empty($data['created_by']) ? trim($data['created_by']) : 'teacher';
$questions = $data['questions'];

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function isValidEmail($email) {
    return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
}

function subjectToHebrew($subjectName) {
    $map = [
        'Math' => 'מתמטיקה',
        'Mathematics' => 'מתמטיקה',
        'Computer Science' => 'מדעי המחשב',
        'ComputerScience' => 'מדעי המחשב',
        'CS' => 'מדעי המחשב',
        'Physics' => 'פיזיקה',
        'Hebrew' => 'עברית',
        'English' => 'אנגלית'
    ];

    return $map[$subjectName] ?? $subjectName;
}

function gradeToHebrew($gradeLevel) {
    if ((int)$gradeLevel === 10) return "י׳";
    if ((int)$gradeLevel === 11) return "י״א";
    if ((int)$gradeLevel === 12) return "י״ב";
    return (string)$gradeLevel;
}

function formatHebrewDate($dateStr) {
    if (empty($dateStr)) {
        return "לא הוגדר";
    }

    $date = DateTime::createFromFormat('Y-m-d', $dateStr);

    if (!$date) {
        return $dateStr;
    }

    return $date->format('d/m/Y');
}

function sendGradeUpEmail($toEmail, $toName, $subject, $htmlBody) {

    if (!isValidEmail($toEmail)) {
        return false;
    }

    if (
        !defined('BREVO_API_KEY') || trim(BREVO_API_KEY) === '' ||
        !defined('MAIL_FROM_EMAIL') || trim(MAIL_FROM_EMAIL) === '' ||
        !defined('MAIL_FROM_NAME') || trim(MAIL_FROM_NAME) === ''
    ) {
        error_log("Brevo config missing in mail_config.php");
        return false;
    }

    if (!function_exists('curl_init')) {
        error_log("cURL is not enabled on server");
        return false;
    }

    $fullBody = '
    <!DOCTYPE html>
    <html lang="he" dir="rtl">
    <head>
        <meta charset="UTF-8">
    </head>
    <body style="direction:rtl; font-family:Arial, sans-serif; background:#fcdbd1; padding:25px; color:#2d3748;">
        <div style="max-width:650px; margin:0 auto; background:#fff9e6; border:1px solid #f3ebd1; border-radius:18px; padding:24px;">
            <h1 style="color:#4a5153; text-align:center; margin-top:0;">GradeUp</h1>
            ' . $htmlBody . '
            <hr style="border:none; border-top:1px solid #edf2f7; margin:25px 0;">
            <p style="font-size:12px; color:#718096; text-align:center;">
                הודעה זו נשלחה אוטומטית ממערכת GradeUp.
            </p>
        </div>
    </body>
    </html>';

    $payload = [
        "sender" => [
            "name" => MAIL_FROM_NAME,
            "email" => MAIL_FROM_EMAIL
        ],
        "to" => [
            [
                "email" => $toEmail,
                "name" => $toName
            ]
        ],
        "subject" => $subject,
        "htmlContent" => $fullBody
    ];

    $ch = curl_init("https://api.brevo.com/v3/smtp/email");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json",
        "api-key: " . BREVO_API_KEY,
        "content-type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($curlError) {
        error_log("Brevo cURL error: " . $curlError);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Brevo API failed. HTTP Code: " . $httpCode . " Response: " . $response);
        return false;
    }

    return true;
}

try {

    /*
        בדיקה שהקבוצה קיימת ושייכת למורה המחובר.
    */
    $groupStmt = $pdo->prepare("
        SELECT 
            tg.group_id,
            tg.teacher_id,
            tg.grade_level,
            s.subject_name,
            CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
        FROM tutoring_groups tg
        JOIN subjects s ON tg.subject_id = s.subject_id
        JOIN teachers t ON tg.teacher_id = t.teacher_id
        WHERE tg.group_id = :group_id
          AND tg.teacher_id = :teacher_id
          AND tg.status = 'approved'
        LIMIT 1
    ");

    $groupStmt->execute([
        ':group_id' => $groupId,
        ':teacher_id' => $teacherId
    ]);

    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        echo json_encode([
            "success" => false,
            "message" => "הקבוצה לא נמצאה או שאינה שייכת למורה המחובר."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
        בדיקה בסיסית של שאלות.
    */
    foreach ($questions as $index => $question) {
        if (
            empty($question['question_text']) ||
            empty($question['option_a']) ||
            empty($question['option_b']) ||
            empty($question['option_c']) ||
            empty($question['option_d']) ||
            empty($question['correct_option'])
        ) {
            echo json_encode([
                "success" => false,
                "message" => "חסרים נתונים בשאלה מספר " . ($index + 1)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!in_array($question['correct_option'], ['a', 'b', 'c', 'd'], true)) {
            echo json_encode([
                "success" => false,
                "message" => "תשובה נכונה לא תקינה בשאלה מספר " . ($index + 1)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $pdo->beginTransaction();

    /*
        יצירת משימה כללית.
    */
    $stmt = $pdo->prepare("
        INSERT INTO assignments
        (group_id, title, description, due_date, created_by)
        VALUES
        (:group_id, :title, :description, :due_date, :created_by)
    ");

    $stmt->execute([
        ':group_id' => $groupId,
        ':title' => $title,
        ':description' => $description,
        ':due_date' => $dueDate,
        ':created_by' => $createdBy
    ]);

    $assignmentId = (int)$pdo->lastInsertId();

    if ($assignmentId <= 0) {
        throw new Exception("לא התקבל מזהה משימה לאחר השמירה.");
    }

    /*
        שמירת שאלות אמריקאיות למשימה.
    */
    $insertQuestion = $pdo->prepare("
        INSERT INTO assignment_questions
        (
            assignment_id,
            question_text,
            option_a,
            option_b,
            option_c,
            option_d,
            correct_option,
            explanation
        )
        VALUES
        (
            :assignment_id,
            :question_text,
            :option_a,
            :option_b,
            :option_c,
            :option_d,
            :correct_option,
            :explanation
        )
    ");

    foreach ($questions as $question) {

        $insertQuestion->execute([
            ':assignment_id' => $assignmentId,
            ':question_text' => trim($question['question_text']),
            ':option_a' => trim($question['option_a']),
            ':option_b' => trim($question['option_b']),
            ':option_c' => trim($question['option_c']),
            ':option_d' => trim($question['option_d']),
            ':correct_option' => trim($question['correct_option']),
            ':explanation' => !empty($question['explanation']) ? trim($question['explanation']) : null
        ]);
    }

    $pdo->commit();

    /*
        אחרי שהמשימה נשמרה בהצלחה:
        שולחים מייל להורי כל תלמידי הקבוצה.
        אם שליחת מייל נכשלת — המשימה עדיין נשארת שמורה.
    */
    $mailResults = [
        "parent_assignment_emails_sent" => 0,
        "failed_emails" => []
    ];

    $parentsStmt = $pdo->prepare("
        SELECT 
            st.student_id,
            st.first_name AS student_first_name,
            st.last_name AS student_last_name,
            st.class_name,
            p.parent_id,
            p.first_name AS parent_first_name,
            p.last_name AS parent_last_name,
            p.email AS parent_email,
            sp.relationship
        FROM tutoring_group_students tgs
        JOIN students st ON tgs.student_id = st.student_id
        JOIN student_parents sp ON st.student_id = sp.student_id
        JOIN parents p ON sp.parent_id = p.parent_id
        WHERE tgs.group_id = :group_id
        ORDER BY st.last_name, st.first_name, p.parent_id
    ");

    $parentsStmt->execute([
        ':group_id' => $groupId
    ]);

    $rows = $parentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $subjectName = subjectToHebrew($group['subject_name']);
    $gradeText = gradeToHebrew($group['grade_level']);
    $teacherName = $group['teacher_name'];
    $dueDateDisplay = formatHebrewDate($dueDate);

    $sentKeys = [];

    foreach ($rows as $row) {

        $studentId = (int)$row['student_id'];
        $studentName = trim($row['student_first_name'] . ' ' . $row['student_last_name']);
        $className = $row['class_name'] ?? '';

        $parentName = trim(($row['parent_first_name'] ?? '') . ' ' . ($row['parent_last_name'] ?? ''));
        $parentEmail = $row['parent_email'] ?? '';

        if ($parentName === '') {
            $parentName = 'הורה';
        }

        /*
            מניעת שליחה כפולה לאותו הורה על אותה משימה עבור אותו תלמיד.
        */
        $sendKey = $assignmentId . '_' . $studentId . '_' . strtolower(trim($parentEmail));

        if (isset($sentKeys[$sendKey])) {
            continue;
        }

        $sentKeys[$sendKey] = true;

        $parentBody = "
            <h2 style='color:#333a42;'>נפתחה משימה חדשה לתלמיד/ה</h2>

            <p>שלום " . h($parentName) . ",</p>

            <p>
                נפתחה משימה חדשה עבור בנך/בתך 
                <strong>" . h($studentName) . "</strong>
                במערכת GradeUp.
            </p>

            <div style='background:white; border:1px solid #edf2f7; border-radius:12px; padding:14px; margin:15px 0;'>
                <p><strong>תלמיד/ה:</strong> " . h($studentName) . "</p>
                <p><strong>כיתה:</strong> " . h($className) . "</p>
                <p><strong>מקצוע:</strong> " . h($subjectName) . "</p>
                <p><strong>שכבה:</strong> " . h($gradeText) . "</p>
                <p><strong>מורה:</strong> " . h($teacherName) . "</p>
                <p><strong>כותרת המשימה:</strong> " . h($title) . "</p>
                <p><strong>תיאור המשימה:</strong> " . h($description) . "</p>
                <p><strong>תאריך הגשה:</strong> " . h($dueDateDisplay) . "</p>
                <p><strong>מספר משימה:</strong> " . h($assignmentId) . "</p>
                <p><strong>מספר קבוצה:</strong> " . h($groupId) . "</p>
            </div>

            <p>
                מומלץ לוודא שהתלמיד/ה נכנס/ת למערכת, פותר/ת את המשימה ומגיש/ה אותה עד לתאריך ההגשה.
            </p>
        ";

        if (isValidEmail($parentEmail)) {
            $sent = sendGradeUpEmail(
                $parentEmail,
                $parentName,
                "נפתחה משימה חדשה במערכת GradeUp",
                $parentBody
            );

            if ($sent) {
                $mailResults["parent_assignment_emails_sent"]++;
            } else {
                $mailResults["failed_emails"][] = "נכשל מייל להורה {$parentName} עבור התלמיד {$studentName}";
            }
        } else {
            $mailResults["failed_emails"][] = "לא נמצא מייל תקין להורה של {$studentName}";
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "המשימה והשאלות נשמרו בהצלחה",
        "assignment_id" => $assignmentId,
        "mail_results" => $mailResults
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
