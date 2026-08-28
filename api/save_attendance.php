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

$groupId = isset($data['group_id']) ? (int)$data['group_id'] : 0;
$sessionDate = trim($data['session_date'] ?? '');
$topic = trim($data['topic'] ?? '');
$attendanceList = $data['attendance'] ?? [];

if ($groupId <= 0 || $sessionDate === '' || $topic === '' || !is_array($attendanceList) || count($attendanceList) === 0) {
    echo json_encode([
        "success" => false,
        "message" => "חסרים נתונים: קבוצה, תאריך, נושא מפגש או רשימת נוכחות."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$dateObject = DateTime::createFromFormat('Y-m-d', $sessionDate);

if (!$dateObject || $dateObject->format('Y-m-d') !== $sessionDate) {
    echo json_encode([
        "success" => false,
        "message" => "תאריך המפגש אינו תקין."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedStatuses = ['present', 'late', 'absent'];

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

function dayToHebrew($day) {
    $map = [
        'Sunday' => 'ראשון',
        'Monday' => 'שני',
        'Tuesday' => 'שלישי',
        'Wednesday' => 'רביעי',
        'Thursday' => 'חמישי',
        'Friday' => 'שישי',
        'Saturday' => 'שבת'
    ];

    return $map[$day] ?? $day;
}

function formatHebrewDate($dateStr) {
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

function getTableColumns($pdo, $tableName) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tableName`");
    $stmt->execute();

    $columns = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }

    return $columns;
}

function findFirstExistingColumn($columns, $possibleNames) {
    foreach ($possibleNames as $name) {
        if (in_array($name, $columns, true)) {
            return $name;
        }
    }

    return null;
}

try {

    /*
        בדיקת שמות עמודות קיימות בטבלאות.
        זה מונע נפילה אם אצלך העמודה נקראת session_id או tutoring_session_id.
    */
    $sessionsColumns = getTableColumns($pdo, 'tutoring_sessions');
    $attendanceColumns = getTableColumns($pdo, 'tutoring_session_attendance');

    $sessionPrimaryColumn = findFirstExistingColumn($sessionsColumns, [
        'session_id',
        'id',
        'tutoring_session_id'
    ]);

    $attendanceSessionColumn = findFirstExistingColumn($attendanceColumns, [
        'session_id',
        'tutoring_session_id',
        'sessionId'
    ]);

    if (!$sessionPrimaryColumn) {
        echo json_encode([
            "success" => false,
            "message" => "לא נמצאה עמודת מזהה בטבלת tutoring_sessions. צריך לבדוק אם היא נקראת session_id או id."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$attendanceSessionColumn) {
        echo json_encode([
            "success" => false,
            "message" => "בטבלת tutoring_session_attendance לא קיימת עמודת קישור למפגש. צריך להוסיף עמודה בשם session_id או tutoring_session_id."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
        שליפת פרטי הקבוצה ובדיקת בעלות:
        רק המורה שמלמד את הקבוצה יכול להזין לה נוכחות.
        בנוסף נשלפים פרטים עבור מייל להורים במקרה של היעדרות.
    */
    $groupStmt = $pdo->prepare("
        SELECT 
            tg.group_id,
            tg.teacher_id,
            tg.day_of_week,
            tg.start_time,
            tg.end_time,
            tg.status,
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
        בדיקה שהתאריך מתאים ליום הקבוע של הקבוצה.
    */
    $groupDay = $group['day_of_week'];
    $selectedDateDay = $dateObject->format('l');

    if ($selectedDateDay !== $groupDay) {
        $dayMapHebrew = [
            'Sunday' => 'ראשון',
            'Monday' => 'שני',
            'Tuesday' => 'שלישי',
            'Wednesday' => 'רביעי',
            'Thursday' => 'חמישי',
            'Friday' => 'שישי',
            'Saturday' => 'שבת'
        ];

        $expectedDayHebrew = $dayMapHebrew[$groupDay] ?? $groupDay;
        $selectedDayHebrew = $dayMapHebrew[$selectedDateDay] ?? $selectedDateDay;

        echo json_encode([
            "success" => false,
            "message" => "לא ניתן לשמור נוכחות ביום {$selectedDayHebrew}. הקבוצה מתקיימת רק ביום {$expectedDayHebrew}."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
        מניעת שמירת נוכחות כפולה לאותה קבוצה באותו תאריך.
    */
    $existingSessionStmt = $pdo->prepare("
        SELECT `$sessionPrimaryColumn`
        FROM tutoring_sessions
        WHERE group_id = :group_id
          AND session_date = :session_date
        LIMIT 1
    ");

    $existingSessionStmt->execute([
        ':group_id' => $groupId,
        ':session_date' => $sessionDate
    ]);

    $existingSession = $existingSessionStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingSession) {
        echo json_encode([
            "success" => false,
            "message" => "כבר נשמרה נוכחות לקבוצה זו בתאריך שנבחר."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
        שליפת תלמידי הקבוצה כדי לוודא שלא נשלח תלמיד זר.
    */
    $studentsStmt = $pdo->prepare("
        SELECT student_id
        FROM tutoring_group_students
        WHERE group_id = :group_id
    ");

    $studentsStmt->execute([
        ':group_id' => $groupId
    ]);

    $groupStudents = $studentsStmt->fetchAll(PDO::FETCH_COLUMN);

    $allowedStudentIds = [];

    foreach ($groupStudents as $studentId) {
        $allowedStudentIds[(int)$studentId] = true;
    }

    if (count($allowedStudentIds) === 0) {
        echo json_encode([
            "success" => false,
            "message" => "לא נמצאו תלמידים בקבוצה."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
        בדיקת תקינות רשימת הנוכחות + איסוף תלמידים חסרים.
    */
    $absentStudentIds = [];

    foreach ($attendanceList as $attendance) {
        $studentId = isset($attendance['student_id']) ? (int)$attendance['student_id'] : 0;
        $status = $attendance['status'] ?? '';

        if ($studentId <= 0 || !isset($allowedStudentIds[$studentId])) {
            echo json_encode([
                "success" => false,
                "message" => "נשלח תלמיד שאינו שייך לקבוצה."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!in_array($status, $allowedStatuses, true)) {
            echo json_encode([
                "success" => false,
                "message" => "סטטוס נוכחות לא תקין."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($status === 'absent') {
            $absentStudentIds[] = $studentId;
        }
    }

    $pdo->beginTransaction();

    /*
        יצירת מפגש תגבור.
    */
    $insertSession = $pdo->prepare("
        INSERT INTO tutoring_sessions
        (group_id, session_date, topic)
        VALUES
        (:group_id, :session_date, :topic)
    ");

    $insertSession->execute([
        ':group_id' => $groupId,
        ':session_date' => $sessionDate,
        ':topic' => $topic
    ]);

    $sessionId = (int)$pdo->lastInsertId();

    if ($sessionId <= 0) {
        throw new Exception("לא התקבל מזהה מפגש לאחר יצירת המפגש.");
    }

    /*
        שמירת נוכחות תלמידים.
        שם עמודת המפגש נבחר אוטומטית לפי מה שקיים אצלך בטבלה.
    */
    $insertAttendance = $pdo->prepare("
        INSERT INTO tutoring_session_attendance
        (`$attendanceSessionColumn`, student_id, status)
        VALUES
        (:session_id, :student_id, :status)
    ");

    foreach ($attendanceList as $attendance) {
        $studentId = (int)$attendance['student_id'];
        $status = $attendance['status'];

        $insertAttendance->execute([
            ':session_id' => $sessionId,
            ':student_id' => $studentId,
            ':status' => $status
        ]);
    }

    $pdo->commit();

    /*
        אחרי שהנוכחות נשמרה בהצלחה:
        שולחים מיילים להורים רק עבור תלמידים שסומנו חסרים.
        אם שליחת מייל נכשלת — הנוכחות עדיין נשארת שמורה.
    */
    $mailResults = [
        "parent_absence_emails_sent" => 0,
        "failed_emails" => []
    ];

    if (count($absentStudentIds) > 0) {

        $placeholders = implode(",", array_fill(0, count($absentStudentIds), "?"));

        $absentInfoStmt = $pdo->prepare("
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
            FROM students st
            INNER JOIN student_parents sp ON st.student_id = sp.student_id
            INNER JOIN parents p ON sp.parent_id = p.parent_id
            WHERE st.student_id IN ($placeholders)
            ORDER BY st.last_name, st.first_name, p.parent_id
        ");

        $absentInfoStmt->execute($absentStudentIds);
        $absenceRows = $absentInfoStmt->fetchAll(PDO::FETCH_ASSOC);

        $subjectName = subjectToHebrew($group['subject_name']);
        $teacherName = $group['teacher_name'];
        $dayHebrew = dayToHebrew($group['day_of_week']);
        $startTime = substr($group['start_time'], 0, 5);
        $endTime = substr($group['end_time'], 0, 5);
        $sessionDateHebrew = formatHebrewDate($sessionDate);

        $sentKeys = [];

        foreach ($absenceRows as $row) {

            $studentId = (int)$row['student_id'];
            $studentName = trim($row['student_first_name'] . ' ' . $row['student_last_name']);
            $className = $row['class_name'] ?? '';

            $parentName = trim(($row['parent_first_name'] ?? '') . ' ' . ($row['parent_last_name'] ?? ''));
            $parentEmail = $row['parent_email'] ?? '';

            if ($parentName === '') {
                $parentName = 'הורה';
            }

            /*
                מניעת שליחת כפול לאותו הורה על אותה היעדרות.
            */
            $sendKey = $studentId . '_' . strtolower(trim($parentEmail)) . '_' . $sessionDate . '_' . $groupId;

            if (isset($sentKeys[$sendKey])) {
                continue;
            }

            $sentKeys[$sendKey] = true;

            $parentBody = "
                <h2 style='color:#333a42;'>עדכון על היעדרות ממפגש תגבור</h2>

                <p>שלום " . h($parentName) . ",</p>

                <p>
                    אנו מעדכנים כי בנך/בתך <strong>" . h($studentName) . "</strong>
                    לא נכח/ה במפגש התגבור שנשמר במערכת.
                </p>

                <div style='background:white; border:1px solid #edf2f7; border-radius:12px; padding:14px; margin:15px 0;'>
                    <p><strong>תלמיד/ה:</strong> " . h($studentName) . "</p>
                    <p><strong>כיתה:</strong> " . h($className) . "</p>
                    <p><strong>מקצוע:</strong> " . h($subjectName) . "</p>
                    <p><strong>מורה:</strong> " . h($teacherName) . "</p>
                    <p><strong>תאריך:</strong> " . h($sessionDateHebrew) . "</p>
                    <p><strong>מועד קבוע:</strong> יום " . h($dayHebrew) . ", " . h($startTime) . "–" . h($endTime) . "</p>
                    <p><strong>נושא המפגש:</strong> " . h($topic) . "</p>
                    <p><strong>מספר קבוצה:</strong> " . h($groupId) . "</p>
                </div>

                <p>
                    מומלץ לוודא מול התלמיד/ה את סיבת ההיעדרות ולהשלים את החומר שנלמד במפגש.
                </p>
            ";

            if (isValidEmail($parentEmail)) {
                $sent = sendGradeUpEmail(
                    $parentEmail,
                    $parentName,
                    "עדכון היעדרות ממפגש תגבור במערכת GradeUp",
                    $parentBody
                );

                if ($sent) {
                    $mailResults["parent_absence_emails_sent"]++;
                } else {
                    $mailResults["failed_emails"][] = "נכשל מייל להורה {$parentName} עבור התלמיד {$studentName}";
                }
            } else {
                $mailResults["failed_emails"][] = "לא נמצא מייל תקין להורה של {$studentName}";
            }
        }

        /*
            אם מסיבה כלשהי אין הורים לתלמיד חסר.
        */
        $studentsWithParentRows = [];

        foreach ($absenceRows as $row) {
            $studentsWithParentRows[(int)$row['student_id']] = true;
        }

        foreach ($absentStudentIds as $absentId) {
            if (!isset($studentsWithParentRows[(int)$absentId])) {
                $mailResults["failed_emails"][] = "לא נמצאו הורים לתלמיד מספר {$absentId}";
            }
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "הנוכחות נשמרה בהצלחה",
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
