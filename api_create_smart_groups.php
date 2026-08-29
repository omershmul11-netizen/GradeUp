<?php
// api_create_smart_groups.php

header("Content-Type: application/json; charset=utf-8");

require_once "db_config.php";
require_once "mail_config.php";

/*
    פונקציה קטנה להגנה על טקסטים שמוכנסים לתוך HTML של המייל.
*/
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
    המרת שמות מקצועות מהמסד לשמות בעברית עבור המיילים.
*/
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

/*
    המרת שכבה מספרית לתצוגה ידידותית.
*/
function gradeToHebrew($gradeLevel) {
    if ((int)$gradeLevel === 10) return "י׳";
    if ((int)$gradeLevel === 11) return "י״א";
    if ((int)$gradeLevel === 12) return "י״ב";
    return (string)$gradeLevel;
}

/*
    המרת יום באנגלית לעברית.
*/
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

/*
    בדיקת תקינות בסיסית של כתובת מייל.
*/
function isValidEmail($email) {
    return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
}

/*
    שליחת מייל HTML דרך Brevo API.
*/
function sendGradeUpEmail($toEmail, $toName, $subject, $htmlBody) {

    if (!isValidEmail($toEmail)) {
        return false;
    }

    if (gradeup_mail_demo_enabled()) {
        return gradeup_record_demo_email($toEmail, $toName, $subject, $htmlBody);
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

    $data = [
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

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

/*
    נרמול שעה:
    אם מגיע 15:00 נהפוך ל-15:00:00.
    אם מגיע 15:00:00 נשאיר כמו שהוא.
*/
function normalizeTimeValue($time) {
    $time = trim((string)$time);

    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time . ":00";
    }

    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return $time;
    }

    return "";
}

/*
    בדיקת תקינות יום ושעה.
    מותר רק ימים ראשון עד חמישי ורק שעות התחלה 15:00 עד 18:00,
    כך שהשיעור מסתיים עד 19:00.
*/
function validateScheduleValues($dayOfWeek, $startTime, $endTime) {

    $allowedDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
    $allowedStartTimes = ['15:00:00', '16:00:00', '17:00:00', '18:00:00'];

    if (!in_array($dayOfWeek, $allowedDays, true)) {
        return false;
    }

    if (!in_array($startTime, $allowedStartTimes, true)) {
        return false;
    }

    $startHour = (int)substr($startTime, 0, 2);
    $expectedEndTime = str_pad((string)($startHour + 1), 2, "0", STR_PAD_LEFT) . ":00:00";

    return $endTime === $expectedEndTime;
}

/*
    בניית מפת מערכת קיימת לפי תלמיד/מורה.
*/
function buildScheduleMap($rows, $idColumnName) {
    $map = [];

    foreach ($rows as $row) {
        $entityId = (int)$row[$idColumnName];
        $day = $row["day_of_week"];
        $time = $row["start_time"];

        if (!isset($map[$entityId])) {
            $map[$entityId] = [];
        }

        if (!isset($map[$entityId][$day])) {
            $map[$entityId][$day] = [];
        }

        $map[$entityId][$day][] = $time;
    }

    return $map;
}

function hasSameHour($scheduleMap, $entityId, $day, $startTime) {
    if (!isset($scheduleMap[$entityId]) || !isset($scheduleMap[$entityId][$day])) {
        return false;
    }

    return in_array($startTime, $scheduleMap[$entityId][$day], true);
}

function countDaySessions($scheduleMap, $entityId, $day) {
    if (!isset($scheduleMap[$entityId]) || !isset($scheduleMap[$entityId][$day])) {
        return 0;
    }

    return count($scheduleMap[$entityId][$day]);
}

function addSlotToMap(&$scheduleMap, $entityId, $day, $startTime) {
    if (!isset($scheduleMap[$entityId])) {
        $scheduleMap[$entityId] = [];
    }

    if (!isset($scheduleMap[$entityId][$day])) {
        $scheduleMap[$entityId][$day] = [];
    }

    $scheduleMap[$entityId][$day][] = $startTime;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input["groups"], $input["grade_level"], $input["subject_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "נתונים חסרים"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$gradeLevel = (int)$input["grade_level"];
$subjectId = (int)$input["subject_id"];
$groups = $input["groups"];

try {

    /*
        שליפת שם המקצוע פעם אחת לפי subject_id.
    */
    $subjectStmt = $pdo->prepare("
        SELECT subject_name
        FROM subjects
        WHERE subject_id = :subject_id
        LIMIT 1
    ");

    $subjectStmt->execute([
        ":subject_id" => $subjectId
    ]);

    $subjectRow = $subjectStmt->fetch(PDO::FETCH_ASSOC);

    if (!$subjectRow) {
        echo json_encode([
            "success" => false,
            "message" => "המקצוע לא נמצא"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $subjectNameRaw = $subjectRow["subject_name"];
    $subjectName = subjectToHebrew($subjectNameRaw);
    $gradeText = gradeToHebrew($gradeLevel);

    /*
        איסוף כל המורים והתלמידים מהשיבוץ לצורך בדיקת התנגשויות לפני שמירה.
    */
    $allTeacherIds = [];
    $allStudentIds = [];

    foreach ($groups as $group) {

        if (empty($group["students"])) {
            continue;
        }

        $teacherId = (int)($group["teacher_id"] ?? 0);

        if ($teacherId > 0) {
            $allTeacherIds[$teacherId] = $teacherId;
        }

        foreach ($group["students"] as $student) {
            $studentId = (int)($student["student_id"] ?? 0);

            if ($studentId > 0) {
                $allStudentIds[$studentId] = $studentId;
            }
        }
    }

    /*
        שליפת מערכת קיימת של תלמידים:
        מונע תלמיד באותה שעה ומונע יותר משני תגבורים ביום.
    */
    $existingStudentScheduleMap = [];

    if (count($allStudentIds) > 0) {
        $studentPlaceholders = implode(",", array_fill(0, count($allStudentIds), "?"));

        $studentScheduleStmt = $pdo->prepare("
            SELECT 
                tgs.student_id,
                tg.day_of_week,
                tg.start_time
            FROM tutoring_group_students tgs
            INNER JOIN tutoring_groups tg ON tgs.group_id = tg.group_id
            WHERE tgs.student_id IN ($studentPlaceholders)
              AND tg.status = 'approved'
        ");

        $studentScheduleStmt->execute(array_values($allStudentIds));
        $studentScheduleRows = $studentScheduleStmt->fetchAll(PDO::FETCH_ASSOC);

        $existingStudentScheduleMap = buildScheduleMap($studentScheduleRows, "student_id");
    }

    /*
        שליפת מערכת קיימת של מורים:
        מונע מורה בשתי קבוצות באותו יום ובאותה שעה.
    */
    $existingTeacherScheduleMap = [];

    if (count($allTeacherIds) > 0) {
        $teacherPlaceholders = implode(",", array_fill(0, count($allTeacherIds), "?"));

        $teacherScheduleStmt = $pdo->prepare("
            SELECT 
                teacher_id,
                day_of_week,
                start_time
            FROM tutoring_groups
            WHERE teacher_id IN ($teacherPlaceholders)
              AND status = 'approved'
        ");

        $teacherScheduleStmt->execute(array_values($allTeacherIds));
        $teacherScheduleRows = $teacherScheduleStmt->fetchAll(PDO::FETCH_ASSOC);

        $existingTeacherScheduleMap = buildScheduleMap($teacherScheduleRows, "teacher_id");
    }

    /*
        שלב 1:
        שמירת הקבוצות והשיבוצים במסד הנתונים.
        כל מה שקשור ל-DB נמצא בתוך טרנזקציה.
    */
    $pdo->beginTransaction();

    $createdGroups = [];
    $createdGroupsForEmails = [];

    /*
        מפות של שיבוצים שנוצרים עכשיו, כדי למנוע התנגשויות גם בתוך אותה לחיצת אישור.
    */
    $proposedStudentScheduleMap = [];
    $proposedTeacherScheduleMap = [];

    foreach ($groups as $group) {

        if (empty($group["students"])) {
            continue;
        }

        $teacherId = (int)($group["teacher_id"] ?? 0);

        if ($teacherId <= 0) {
            throw new Exception("חסר teacher_id באחת הקבוצות");
        }

        /*
            היום והשעה נלקחים מהשיבוץ החכם שהגיע מה-Preview.
        */
        $dayOfWeek = $group["day_of_week"] ?? "";
        $startTime = normalizeTimeValue($group["start_time"] ?? "");
        $endTime = normalizeTimeValue($group["end_time"] ?? "");

        if (!validateScheduleValues($dayOfWeek, $startTime, $endTime)) {
            throw new Exception("יום או שעה לא תקינים באחת הקבוצות. מותר לשבץ רק בין 15:00 ל-19:00 בימים ראשון עד חמישי.");
        }

        /*
            בדיקת התנגשות מורה מול קבוצות קיימות ומול קבוצות שנוצרות עכשיו.
        */
        if (hasSameHour($existingTeacherScheduleMap, $teacherId, $dayOfWeek, $startTime)) {
            throw new Exception("המורה משובץ כבר לקבוצה אחרת באותו יום ובאותה שעה.");
        }

        if (hasSameHour($proposedTeacherScheduleMap, $teacherId, $dayOfWeek, $startTime)) {
            throw new Exception("המורה שובץ פעמיים באותו יום ובאותה שעה באותו שיבוץ.");
        }

        /*
            בדיקת תלמידים:
            1. לא באותה שעה.
            2. עד 2 תגבורים ביום.
        */
        foreach ($group["students"] as $student) {

            $studentId = (int)($student["student_id"] ?? 0);

            if ($studentId <= 0) {
                throw new Exception("חסר student_id באחת הקבוצות");
            }

            if (hasSameHour($existingStudentScheduleMap, $studentId, $dayOfWeek, $startTime)) {
                throw new Exception("אחד התלמידים משובץ כבר לקבוצה אחרת באותו יום ובאותה שעה.");
            }

            if (hasSameHour($proposedStudentScheduleMap, $studentId, $dayOfWeek, $startTime)) {
                throw new Exception("אחד התלמידים שובץ פעמיים באותה שעה באותו שיבוץ.");
            }

            $existingCount = countDaySessions($existingStudentScheduleMap, $studentId, $dayOfWeek);
            $proposedCount = countDaySessions($proposedStudentScheduleMap, $studentId, $dayOfWeek);

            if (($existingCount + $proposedCount) >= 2) {
                throw new Exception("אחד התלמידים כבר הגיע למגבלה של שני תגבורים ביום.");
            }
        }

        $status = "approved";

        /*
            יצירת קבוצת תגבור.
        */
        $insertGroup = $pdo->prepare("
            INSERT INTO tutoring_groups 
            (subject_id, grade_level, teacher_id, day_of_week, start_time, end_time, status)
            VALUES
            (:subject_id, :grade_level, :teacher_id, :day_of_week, :start_time, :end_time, :status)
        ");

        $insertGroup->execute([
            ":subject_id" => $subjectId,
            ":grade_level" => $gradeLevel,
            ":teacher_id" => $teacherId,
            ":day_of_week" => $dayOfWeek,
            ":start_time" => $startTime,
            ":end_time" => $endTime,
            ":status" => $status
        ]);

        $groupId = (int)$pdo->lastInsertId();

        /*
            שיוך תלמידים לקבוצה.
        */
        $insertStudent = $pdo->prepare("
            INSERT INTO tutoring_group_students 
            (group_id, student_id)
            VALUES 
            (:group_id, :student_id)
        ");

        $studentIds = [];

        foreach ($group["students"] as $student) {

            $studentId = (int)$student["student_id"];
            $studentIds[] = $studentId;

            $insertStudent->execute([
                ":group_id" => $groupId,
                ":student_id" => $studentId
            ]);

            addSlotToMap($proposedStudentScheduleMap, $studentId, $dayOfWeek, $startTime);
        }

        addSlotToMap($proposedTeacherScheduleMap, $teacherId, $dayOfWeek, $startTime);

        /*
            שליפת פרטי המורה, כולל מייל.
        */
        $teacherStmt = $pdo->prepare("
            SELECT teacher_id, first_name, last_name, email
            FROM teachers
            WHERE teacher_id = :teacher_id
            LIMIT 1
        ");

        $teacherStmt->execute([
            ":teacher_id" => $teacherId
        ]);

        $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);

        $teacherName = "";
        $teacherEmail = "";

        if ($teacher) {
            $teacherName = trim($teacher["first_name"] . " " . $teacher["last_name"]);
            $teacherEmail = $teacher["email"] ?? "";
        } else {
            $teacherName = $group["teacher_name"] ?? "";
        }

        /*
            שליפת פרטי התלמידים, כולל מיילים.
        */
        $studentsFull = [];

        if (count($studentIds) > 0) {

            $placeholders = implode(",", array_fill(0, count($studentIds), "?"));

            $studentsStmt = $pdo->prepare("
                SELECT student_id, first_name, last_name, email, class_name
                FROM students
                WHERE student_id IN ($placeholders)
                ORDER BY last_name, first_name
            ");

            $studentsStmt->execute($studentIds);
            $studentsFull = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /*
            שליפת ההורים של תלמידי הקבוצה.
            נשמר לפי student_id כדי שנוכל לשלוח מייל מותאם לכל הורה על הילד שלו.
        */
        $parentsByStudentId = [];

        if (count($studentIds) > 0) {

            $parentPlaceholders = implode(",", array_fill(0, count($studentIds), "?"));

            $parentsStmt = $pdo->prepare("
                SELECT 
                    sp.student_id,
                    p.parent_id,
                    p.first_name,
                    p.last_name,
                    p.email,
                    sp.relationship
                FROM student_parents sp
                INNER JOIN parents p ON sp.parent_id = p.parent_id
                WHERE sp.student_id IN ($parentPlaceholders)
                ORDER BY sp.student_id, p.parent_id
            ");

            $parentsStmt->execute($studentIds);
            $parentsRows = $parentsStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($parentsRows as $parentRow) {
                $sid = (int)$parentRow["student_id"];

                if (!isset($parentsByStudentId[$sid])) {
                    $parentsByStudentId[$sid] = [];
                }

                $parentsByStudentId[$sid][] = $parentRow;
            }
        }

        $createdGroups[] = [
            "group_id" => $groupId,
            "teacher_id" => $teacherId,
            "teacher_name" => $teacherName,
            "student_count" => count($studentIds),
            "day_of_week" => $dayOfWeek,
            "start_time" => $startTime,
            "end_time" => $endTime
        ];

        /*
            שומרים בצד את כל המידע הדרוש לשליחת מיילים אחרי ה-commit.
        */
        $createdGroupsForEmails[] = [
            "group_id" => $groupId,
            "teacher_id" => $teacherId,
            "teacher_name" => $teacherName,
            "teacher_email" => $teacherEmail,
            "students" => $studentsFull,
            "parents_by_student_id" => $parentsByStudentId,
            "subject_name" => $subjectName,
            "grade_text" => $gradeText,
            "day_of_week" => $dayOfWeek,
            "day_hebrew" => dayToHebrew($dayOfWeek),
            "start_time" => $startTime,
            "end_time" => $endTime
        ];
    }

    $pdo->commit();

    /*
        שלב 2:
        שליחת מיילים אחרי שהקבוצות נשמרו בהצלחה.
        חשוב: אם מייל נכשל, הקבוצה עדיין נשארת שמורה.
    */
    $mailResults = [
        "teacher_emails_sent" => 0,
        "student_emails_sent" => 0,
        "parent_emails_sent" => 0,
        "failed_emails" => []
    ];

    foreach ($createdGroupsForEmails as $createdGroup) {

        $groupId = $createdGroup["group_id"];
        $teacherName = $createdGroup["teacher_name"];
        $teacherEmail = $createdGroup["teacher_email"];
        $studentsFull = $createdGroup["students"];
        $parentsByStudentId = $createdGroup["parents_by_student_id"];

        $subjectName = $createdGroup["subject_name"];
        $gradeText = $createdGroup["grade_text"];
        $dayHebrew = $createdGroup["day_hebrew"];
        $startTime = substr($createdGroup["start_time"], 0, 5);
        $endTime = substr($createdGroup["end_time"], 0, 5);

        /*
            מייל למורה.
        */
        $studentsListHtml = "<ul style='line-height:1.8;'>";

        foreach ($studentsFull as $student) {
            $studentsListHtml .= "<li>" .
                h($student["first_name"] . " " . $student["last_name"]) .
                " | כיתה " . h($student["class_name"] ?? "") .
                "</li>";
        }

        $studentsListHtml .= "</ul>";

        $teacherBody = "
            <h2 style='color:#333a42;'>נפתחה עבורך קבוצת תגבור חדשה</h2>

            <p>שלום " . h($teacherName) . ",</p>

            <p>שובצת כמורה אחראי לקבוצת תגבור חדשה במערכת.</p>

            <div style='background:white; border:1px solid #edf2f7; border-radius:12px; padding:14px; margin:15px 0;'>
                <p><strong>מספר קבוצה:</strong> " . h($groupId) . "</p>
                <p><strong>מקצוע:</strong> " . h($subjectName) . "</p>
                <p><strong>שכבה:</strong> " . h($gradeText) . "</p>
                <p><strong>מועד:</strong> יום " . h($dayHebrew) . ", " . h($startTime) . "–" . h($endTime) . "</p>
            </div>

            <h3 style='color:#333a42;'>רשימת תלמידים בקבוצה:</h3>
            {$studentsListHtml}

            <p>ניתן להיכנס למערכת כדי לנהל נוכחות, ליצור משימות ולעקוב אחר הגשות.</p>
        ";

        if (isValidEmail($teacherEmail)) {
            $sent = sendGradeUpEmail(
                $teacherEmail,
                $teacherName,
                "נפתחה עבורך קבוצת תגבור חדשה במערכת GradeUp",
                $teacherBody
            );

            if ($sent) {
                $mailResults["teacher_emails_sent"]++;
            } else {
                $mailResults["failed_emails"][] = "נכשל מייל למורה {$teacherName} בקבוצה {$groupId}";
            }
        } else {
            $mailResults["failed_emails"][] = "לא נמצא מייל תקין למורה {$teacherName} בקבוצה {$groupId}";
        }

        /*
            מייל לכל תלמיד + מייל להורה/הורים של כל תלמיד.
        */
        $sentParentEmailsForGroup = [];

        foreach ($studentsFull as $student) {

            $studentId = (int)$student["student_id"];
            $studentName = trim($student["first_name"] . " " . $student["last_name"]);
            $studentEmail = $student["email"] ?? "";

            $studentBody = "
                <h2 style='color:#333a42;'>שובצת לקבוצת תגבור חדשה</h2>

                <p>שלום " . h($studentName) . ",</p>

                <p>שובצת לקבוצת תגבור חדשה במערכת GradeUp.</p>

                <div style='background:white; border:1px solid #edf2f7; border-radius:12px; padding:14px; margin:15px 0;'>
                    <p><strong>מקצוע:</strong> " . h($subjectName) . "</p>
                    <p><strong>שכבה:</strong> " . h($gradeText) . "</p>
                    <p><strong>מורה:</strong> " . h($teacherName) . "</p>
                    <p><strong>מועד:</strong> יום " . h($dayHebrew) . ", " . h($startTime) . "–" . h($endTime) . "</p>
                    <p><strong>מספר קבוצה:</strong> " . h($groupId) . "</p>
                </div>

                <p>בהמשך יופיעו עבורך משימות ודפי עבודה באזור התלמיד במערכת.</p>
            ";

            if (isValidEmail($studentEmail)) {
                $sent = sendGradeUpEmail(
                    $studentEmail,
                    $studentName,
                    "שובצת לקבוצת תגבור חדשה במערכת GradeUp",
                    $studentBody
                );

                if ($sent) {
                    $mailResults["student_emails_sent"]++;
                } else {
                    $mailResults["failed_emails"][] = "נכשל מייל לתלמיד {$studentName} בקבוצה {$groupId}";
                }
            } else {
                $mailResults["failed_emails"][] = "לא נמצא מייל תקין לתלמיד {$studentName} בקבוצה {$groupId}";
            }

            /*
                מייל להורי התלמיד.
            */
            $studentParents = $parentsByStudentId[$studentId] ?? [];

            if (count($studentParents) === 0) {
                $mailResults["failed_emails"][] = "לא נמצאו הורים לתלמיד {$studentName} בקבוצה {$groupId}";
                continue;
            }

            foreach ($studentParents as $parent) {

                $parentEmail = $parent["email"] ?? "";
                $parentName = trim(($parent["first_name"] ?? "") . " " . ($parent["last_name"] ?? ""));

                if ($parentName === "") {
                    $parentName = "הורה";
                }

                /*
                    מניעת שליחת כפול לאותו הורה על אותו תלמיד ואותה קבוצה.
                    שימושי אם בעתיד יהיו קישורים כפולים או אחים.
                */
                $parentSendKey = $groupId . "_" . $studentId . "_" . strtolower(trim($parentEmail));

                if (isset($sentParentEmailsForGroup[$parentSendKey])) {
                    continue;
                }

                $sentParentEmailsForGroup[$parentSendKey] = true;

                $parentBody = "
                    <h2 style='color:#333a42;'>עדכון על שיבוץ לקבוצת תגבור</h2>

                    <p>שלום " . h($parentName) . ",</p>

                    <p>
                        בנך/בתך <strong>" . h($studentName) . "</strong>
                        שובץ/ה לקבוצת תגבור חדשה במערכת GradeUp.
                    </p>

                    <div style='background:white; border:1px solid #edf2f7; border-radius:12px; padding:14px; margin:15px 0;'>
                        <p><strong>מקצוע:</strong> " . h($subjectName) . "</p>
                        <p><strong>שכבה:</strong> " . h($gradeText) . "</p>
                        <p><strong>מורה:</strong> " . h($teacherName) . "</p>
                        <p><strong>מועד:</strong> יום " . h($dayHebrew) . ", " . h($startTime) . "–" . h($endTime) . "</p>
                        <p><strong>מספר קבוצה:</strong> " . h($groupId) . "</p>
                    </div>

                    <p>
                        מטרת קבוצת התגבור היא לחזק את התלמיד/ה בתחום הלימודי ולסייע במעקב אחר התקדמותו/ה.
                    </p>
                ";

                if (isValidEmail($parentEmail)) {
                    $sent = sendGradeUpEmail(
                        $parentEmail,
                        $parentName,
                        "עדכון: שיבוץ לקבוצת תגבור במערכת GradeUp",
                        $parentBody
                    );

                    if ($sent) {
                        $mailResults["parent_emails_sent"]++;
                    } else {
                        $mailResults["failed_emails"][] = "נכשל מייל להורה {$parentName} עבור התלמיד {$studentName} בקבוצה {$groupId}";
                    }
                } else {
                    $mailResults["failed_emails"][] = "לא נמצא מייל תקין להורה של {$studentName} בקבוצה {$groupId}";
                }
            }
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "הקבוצות נשמרו בהצלחה. ניסיון שליחת המיילים למורה, לתלמידים ולהורים הסתיים.",
        "created_groups" => $createdGroups,
        "mail_results" => $mailResults
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        "success" => false,
        "message" => "שגיאה ביצירת הקבוצות",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
