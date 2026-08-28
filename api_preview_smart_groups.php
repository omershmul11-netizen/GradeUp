<?php
// api_preview_smart_groups.php

header("Content-Type: application/json; charset=utf-8");

require_once "db_config.php";

$openaiConfigPath = __DIR__ . "/openai_config.php";
if (file_exists($openaiConfigPath)) {
    require_once $openaiConfigPath;
}

$gradeLevel = isset($_GET["grade_level"]) ? (int)$_GET["grade_level"] : 0;
$subjectId = isset($_GET["subject_id"]) ? (int)$_GET["subject_id"] : 0;
$shuffleSeed = isset($_GET["shuffle"]) ? (int)$_GET["shuffle"] : 0;

if (!$gradeLevel || !$subjectId) {
    echo json_encode([
        "success" => false,
        "message" => "חסר grade_level או subject_id"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function calculateGroupCount($studentCount) {

    if ($studentCount <= 0) {
        return 0;
    }

    if ($studentCount <= 10) {
        return 1;
    }

    $minGroups = (int)ceil($studentCount / 10);
    $maxGroups = (int)ceil($studentCount / 8);

    for ($groups = $minGroups; $groups <= $maxGroups; $groups++) {
        $avg = $studentCount / $groups;

        if ($avg >= 8 && $avg <= 10) {
            return $groups;
        }
    }

    return $minGroups;
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

function getAvailableSlots($shuffleSeed = 0) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
    $times = ['15:00:00', '16:00:00', '17:00:00', '18:00:00'];

    $slots = [];

    foreach ($days as $day) {
        foreach ($times as $startTime) {
            $hour = (int)substr($startTime, 0, 2);
            $endHour = $hour + 1;
            $endTime = str_pad((string)$endHour, 2, "0", STR_PAD_LEFT) . ":00:00";

            $slots[] = [
                "day_of_week" => $day,
                "start_time" => $startTime,
                "end_time" => $endTime
            ];
        }
    }

    if ($shuffleSeed > 0) {
        mt_srand($shuffleSeed);
        shuffle($slots);
    }

    return $slots;
}

function addTeacherHistoryToStudents(&$students, $teacherHistory) {
    foreach ($students as &$student) {
        $studentId = (int)$student["student_id"];
        $student["teacher_history"] = $teacherHistory[$studentId] ?? [];
    }
}

function getPreferredTeacherId($student, $teachers) {
    $history = $student["teacher_history"] ?? [];

    $bestTeacherId = 0;
    $bestCount = 0;

    foreach ($teachers as $teacher) {
        $teacherId = (int)$teacher["teacher_id"];
        $count = isset($history[$teacherId]) ? (int)$history[$teacherId] : 0;

        if ($count > $bestCount) {
            $bestCount = $count;
            $bestTeacherId = $teacherId;
        }
    }

    return $bestTeacherId;
}

function buildInternalSmartGroups($students, $teachers, $groupCount, $shuffleSeed = 0) {

    if ($shuffleSeed > 0) {
        mt_srand($shuffleSeed);
        shuffle($students);
    }

    /*
        מיון לפי מורה מועדף, ואז לפי ציון.
        זה עוזר לקבץ תלמידים שלמדו בעבר עם אותו מורה.
    */
    usort($students, function($a, $b) use ($teachers) {
        $prefA = getPreferredTeacherId($a, $teachers);
        $prefB = getPreferredTeacherId($b, $teachers);

        if ($prefA !== $prefB) {
            return $prefA <=> $prefB;
        }

        return (int)$a["latest_grade"] <=> (int)$b["latest_grade"];
    });

    $groups = [];

    for ($i = 0; $i < $groupCount; $i++) {
        $teacher = $teachers[$i % count($teachers)];

        $groups[$i] = [
            "group_number" => $i + 1,
            "teacher_id" => (int)$teacher["teacher_id"],
            "teacher_name" => $teacher["first_name"] . " " . $teacher["last_name"],
            "teacher_email" => $teacher["email"],
            "students" => []
        ];
    }

    foreach ($students as $student) {

        $preferredTeacherId = getPreferredTeacherId($student, $teachers);

        $bestGroupIndex = null;
        $bestScore = -999999;

        foreach ($groups as $index => $group) {

            if (count($group["students"]) >= 10) {
                continue;
            }

            $score = 0;

            /*
                עדיפות חזקה לקבוצה של מורה שהתלמיד כבר למד איתו.
            */
            if ($preferredTeacherId > 0 && (int)$group["teacher_id"] === $preferredTeacherId) {
                $score += 100;
            }

            /*
                איזון גודל קבוצות.
            */
            $score -= count($group["students"]) * 5;

            /*
                איזון לפי ציונים.
            */
            if (count($group["students"]) > 0) {
                $sum = 0;

                foreach ($group["students"] as $existingStudent) {
                    $sum += (int)$existingStudent["latest_grade"];
                }

                $avg = $sum / count($group["students"]);
                $score -= abs($avg - (int)$student["latest_grade"]);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestGroupIndex = $index;
            }
        }

        if ($bestGroupIndex === null) {
            $bestGroupIndex = 0;

            foreach ($groups as $index => $group) {
                if (count($group["students"]) < count($groups[$bestGroupIndex]["students"])) {
                    $bestGroupIndex = $index;
                }
            }
        }

        $groups[$bestGroupIndex]["students"][] = [
            "student_id" => (int)$student["student_id"],
            "first_name" => $student["first_name"],
            "last_name" => $student["last_name"],
            "grade_level" => (int)$student["grade_level"],
            "class_name" => $student["class_name"],
            "email" => $student["email"],
            "latest_grade" => (int)$student["latest_grade"]
        ];
    }

    return $groups;
}

function tryBuildGroupsWithGpt($students, $teachers, $groupCount, $gradeLevel, $subjectName, $shuffleSeed) {

    if (!defined("OPENAI_API_KEY") || trim(OPENAI_API_KEY) === "") {
        return null;
    }

    $studentsForAi = [];

    foreach ($students as $student) {
        $studentsForAi[] = [
            "student_id" => (int)$student["student_id"],
            "name" => $student["first_name"] . " " . $student["last_name"],
            "class_name" => $student["class_name"],
            "latest_grade" => (int)$student["latest_grade"],
            "teacher_history" => $student["teacher_history"] ?? []
        ];
    }

    $teachersForAi = [];

    foreach ($teachers as $teacher) {
        $teachersForAi[] = [
            "teacher_id" => (int)$teacher["teacher_id"],
            "name" => $teacher["first_name"] . " " . $teacher["last_name"]
        ];
    }

    $prompt = "
אתה מנוע שיבוץ חכם למערכת תגבורים בתיכון.

עליך לחלק תלמידים לקבוצות תגבור לפי הכללים הבאים:

1. השיבוץ הוא רק לשכבה: {$gradeLevel}.
2. השיבוץ הוא רק למקצוע: {$subjectName}.
3. יש ליצור בדיוק {$groupCount} קבוצות.
4. בכל קבוצה יהיו לכל היותר 10 תלמידים.
5. רצוי שכל קבוצה תהיה בין 8 ל-10 תלמידים אם זה אפשרי לפי מספר התלמידים.
6. אסור לשבץ תלמיד ביותר מקבוצה אחת.
7. יש לשבץ לכל קבוצה מורה מתוך רשימת המורים בלבד.
8. עדיפות גבוהה: אם לתלמיד יש teacher_history, נסה לשבץ אותו בקבוצה של מורה שהוא כבר למד איתו בעבר.
9. נסה לאזן בין הקבוצות לפי ציונים כך שלא תהיה קבוצה חזקה מאוד וקבוצה חלשה מאוד.
10. החזר JSON בלבד, בלי Markdown ובלי הסברים.

רשימת מורים:
" . json_encode($teachersForAi, JSON_UNESCAPED_UNICODE) . "

רשימת תלמידים:
" . json_encode($studentsForAi, JSON_UNESCAPED_UNICODE) . "

מבנה JSON מדויק:
{
  \"groups\": [
    {
      \"teacher_id\": 1,
      \"student_ids\": [1,2,3]
    }
  ]
}
";

    $payload = [
        "model" => "gpt-4.1-mini",
        "input" => [
            [
                "role" => "user",
                "content" => $prompt
            ]
        ],
        "text" => [
            "format" => [
                "type" => "json_object"
            ]
        ],
        "temperature" => 0.35
    ];

    $ch = curl_init("https://api.openai.com/v1/responses");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . trim(OPENAI_API_KEY)
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $openaiResult = json_decode($response, true);

    $outputText = $openaiResult["output_text"] ?? null;

    if (!$outputText && isset($openaiResult["output"]) && is_array($openaiResult["output"])) {
        foreach ($openaiResult["output"] as $outputItem) {
            if (!empty($outputItem["content"]) && is_array($outputItem["content"])) {
                foreach ($outputItem["content"] as $contentItem) {
                    if (isset($contentItem["text"])) {
                        $outputText = $contentItem["text"];
                        break 2;
                    }
                }
            }
        }
    }

    if (!$outputText) {
        return null;
    }

    $aiJson = json_decode($outputText, true);

    if (!$aiJson || empty($aiJson["groups"]) || !is_array($aiJson["groups"])) {
        return null;
    }

    $studentsById = [];

    foreach ($students as $student) {
        $studentsById[(int)$student["student_id"]] = $student;
    }

    $teachersById = [];

    foreach ($teachers as $teacher) {
        $teachersById[(int)$teacher["teacher_id"]] = $teacher;
    }

    $usedStudentIds = [];
    $groups = [];

    foreach ($aiJson["groups"] as $index => $aiGroup) {

        $teacherId = (int)($aiGroup["teacher_id"] ?? 0);

        if (!isset($teachersById[$teacherId])) {
            return null;
        }

        if (empty($aiGroup["student_ids"]) || !is_array($aiGroup["student_ids"])) {
            return null;
        }

        $teacher = $teachersById[$teacherId];

        $group = [
            "group_number" => $index + 1,
            "teacher_id" => $teacherId,
            "teacher_name" => $teacher["first_name"] . " " . $teacher["last_name"],
            "teacher_email" => $teacher["email"],
            "students" => []
        ];

        foreach ($aiGroup["student_ids"] as $studentIdRaw) {

            $studentId = (int)$studentIdRaw;

            if (!isset($studentsById[$studentId])) {
                return null;
            }

            if (isset($usedStudentIds[$studentId])) {
                return null;
            }

            $usedStudentIds[$studentId] = true;
            $student = $studentsById[$studentId];

            $group["students"][] = [
                "student_id" => (int)$student["student_id"],
                "first_name" => $student["first_name"],
                "last_name" => $student["last_name"],
                "grade_level" => (int)$student["grade_level"],
                "class_name" => $student["class_name"],
                "email" => $student["email"],
                "latest_grade" => (int)$student["latest_grade"]
            ];
        }

        if (count($group["students"]) > 10) {
            return null;
        }

        $groups[] = $group;
    }

    if (count($groups) !== $groupCount) {
        return null;
    }

    if (count($usedStudentIds) !== count($students)) {
        return null;
    }

    return $groups;
}

function buildExistingScheduleMap($existingRows, $idColumnName) {
    $map = [];

    foreach ($existingRows as $row) {
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

function countDaySessions($scheduleMap, $entityId, $day) {
    if (!isset($scheduleMap[$entityId]) || !isset($scheduleMap[$entityId][$day])) {
        return 0;
    }

    return count($scheduleMap[$entityId][$day]);
}

function hasSameHour($scheduleMap, $entityId, $day, $startTime) {
    if (!isset($scheduleMap[$entityId]) || !isset($scheduleMap[$entityId][$day])) {
        return false;
    }

    return in_array($startTime, $scheduleMap[$entityId][$day], true);
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

function assignSchedulesToGroups(&$groups, $existingStudentScheduleMap, $existingTeacherScheduleMap, $shuffleSeed = 0) {

    $slots = getAvailableSlots($shuffleSeed);

    $proposedStudentScheduleMap = [];
    $proposedTeacherScheduleMap = [];

    foreach ($groups as &$group) {

        $assignedSlot = null;
        $teacherId = (int)$group["teacher_id"];

        foreach ($slots as $slot) {

            $day = $slot["day_of_week"];
            $startTime = $slot["start_time"];

            $slotOk = true;

            /*
                בדיקת מורה:
                אסור שלמורה תהיה כבר קבוצה באותו יום ובאותה שעה.
                זה כולל קבוצות קיימות במסד וגם קבוצות מוצעות באותו שיבוץ.
            */
            if (hasSameHour($existingTeacherScheduleMap, $teacherId, $day, $startTime)) {
                $slotOk = false;
            }

            if (hasSameHour($proposedTeacherScheduleMap, $teacherId, $day, $startTime)) {
                $slotOk = false;
            }

            if (!$slotOk) {
                continue;
            }

            /*
                בדיקת תלמידים:
                1. תלמיד לא יכול להיות בשתי קבוצות באותו יום ובאותה שעה.
                2. תלמיד יכול להיות עד 2 תגבורים ביום.
            */
            foreach ($group["students"] as $student) {

                $studentId = (int)$student["student_id"];

                if (hasSameHour($existingStudentScheduleMap, $studentId, $day, $startTime)) {
                    $slotOk = false;
                    break;
                }

                if (hasSameHour($proposedStudentScheduleMap, $studentId, $day, $startTime)) {
                    $slotOk = false;
                    break;
                }

                $existingCount = countDaySessions($existingStudentScheduleMap, $studentId, $day);
                $proposedCount = countDaySessions($proposedStudentScheduleMap, $studentId, $day);

                if (($existingCount + $proposedCount) >= 2) {
                    $slotOk = false;
                    break;
                }
            }

            if ($slotOk) {
                $assignedSlot = $slot;
                break;
            }
        }

        if (!$assignedSlot) {
            return [
                "success" => false,
                "message" => "לא נמצא יום/שעה פנויים עבור אחת הקבוצות לפי מגבלות תלמידים ומורים."
            ];
        }

        $group["day_of_week"] = $assignedSlot["day_of_week"];
        $group["start_time"] = $assignedSlot["start_time"];
        $group["end_time"] = $assignedSlot["end_time"];

        $day = $assignedSlot["day_of_week"];
        $startTime = $assignedSlot["start_time"];

        addSlotToMap($proposedTeacherScheduleMap, $teacherId, $day, $startTime);

        foreach ($group["students"] as $student) {
            $studentId = (int)$student["student_id"];
            addSlotToMap($proposedStudentScheduleMap, $studentId, $day, $startTime);
        }
    }

    return [
        "success" => true,
        "message" => "שובצו מועדים בהצלחה"
    ];
}

function calculateAverages(&$groups) {
    foreach ($groups as &$group) {
        $sum = 0;

        foreach ($group["students"] as $student) {
            $sum += (int)$student["latest_grade"];
        }

        $group["average_grade"] = count($group["students"]) > 0
            ? round($sum / count($group["students"]), 1)
            : 0;
    }
}

try {

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
            "message" => "המקצוע שנבחר לא נמצא"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $subjectNameRaw = $subjectRow["subject_name"];
    $subjectName = subjectToHebrew($subjectNameRaw);

    /*
        שליפת תלמידים בטווח 60-70 לפי שכבה ומקצוע.
        תלמיד שכבר משובץ לקבוצה מאושרת באותו מקצוע לא ישובץ שוב לאותו מקצוע.
    */
    $stmt = $pdo->prepare("
        SELECT 
            s.student_id,
            s.first_name,
            s.last_name,
            s.grade_level,
            s.class_name,
            s.email,
            g.latest_grade
        FROM students s
        INNER JOIN student_subject_grades g ON s.student_id = g.student_id
        WHERE s.grade_level = :grade_level
          AND g.subject_id = :subject_id
          AND g.latest_grade BETWEEN 60 AND 70
          AND s.student_id NOT IN (
                SELECT tgs.student_id
                FROM tutoring_group_students tgs
                INNER JOIN tutoring_groups tg ON tgs.group_id = tg.group_id
                WHERE tg.subject_id = :subject_id_exclude
                  AND tg.status = 'approved'
          )
        ORDER BY g.latest_grade ASC, s.class_name ASC, s.last_name ASC, s.first_name ASC
    ");

    $stmt->execute([
        ":grade_level" => $gradeLevel,
        ":subject_id" => $subjectId,
        ":subject_id_exclude" => $subjectId
    ]);

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($students) === 0) {
        echo json_encode([
            "success" => false,
            "message" => "לא נמצאו תלמידים מתאימים לשיבוץ"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
        שליפת מורים מתאימים לפי שכבה ומקצוע.
    */
    $teacherStmt = $pdo->prepare("
        SELECT 
            t.teacher_id,
            t.first_name,
            t.last_name,
            t.email
        FROM teachers t
        INNER JOIN teacher_subjects ts ON t.teacher_id = ts.teacher_id
        WHERE ts.grade_level = :grade_level
          AND ts.subject_id = :subject_id
        GROUP BY t.teacher_id, t.first_name, t.last_name, t.email
        ORDER BY t.teacher_id ASC
    ");

    $teacherStmt->execute([
        ":grade_level" => $gradeLevel,
        ":subject_id" => $subjectId
    ]);

    $teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($teachers) === 0) {
        echo json_encode([
            "success" => false,
            "message" => "לא נמצאו מורים מתאימים לשכבה ולמקצוע"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $studentIds = array_map(function($student) {
        return (int)$student["student_id"];
    }, $students);

    $teacherIds = array_map(function($teacher) {
        return (int)$teacher["teacher_id"];
    }, $teachers);

    /*
        שליפת היסטוריית מורים של התלמידים.
        זה מאפשר ל-GPT ולמנוע הפנימי להעדיף תלמידים שכבר למדו עם אותו מורה.
    */
    $teacherHistory = [];

    if (count($studentIds) > 0) {

        $placeholders = implode(",", array_fill(0, count($studentIds), "?"));

        $historyStmt = $pdo->prepare("
            SELECT 
                tgs.student_id,
                tg.teacher_id,
                COUNT(*) AS cnt
            FROM tutoring_group_students tgs
            INNER JOIN tutoring_groups tg ON tgs.group_id = tg.group_id
            WHERE tgs.student_id IN ($placeholders)
              AND tg.status = 'approved'
            GROUP BY tgs.student_id, tg.teacher_id
        ");

        $historyStmt->execute($studentIds);
        $historyRows = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($historyRows as $row) {
            $studentId = (int)$row["student_id"];
            $teacherId = (int)$row["teacher_id"];

            if (!isset($teacherHistory[$studentId])) {
                $teacherHistory[$studentId] = [];
            }

            $teacherHistory[$studentId][$teacherId] = (int)$row["cnt"];
        }
    }

    addTeacherHistoryToStudents($students, $teacherHistory);

    /*
        שליפת מערכת קיימת של התלמידים:
        מונע תלמיד באותה שעה ומונע יותר משני תגבורים ביום.
    */
    $existingStudentScheduleMap = [];

    if (count($studentIds) > 0) {

        $placeholders = implode(",", array_fill(0, count($studentIds), "?"));

        $studentScheduleStmt = $pdo->prepare("
            SELECT 
                tgs.student_id,
                tg.day_of_week,
                tg.start_time
            FROM tutoring_group_students tgs
            INNER JOIN tutoring_groups tg ON tgs.group_id = tg.group_id
            WHERE tgs.student_id IN ($placeholders)
              AND tg.status = 'approved'
        ");

        $studentScheduleStmt->execute($studentIds);
        $studentScheduleRows = $studentScheduleStmt->fetchAll(PDO::FETCH_ASSOC);

        $existingStudentScheduleMap = buildExistingScheduleMap($studentScheduleRows, "student_id");
    }

    /*
        שליפת מערכת קיימת של המורים:
        מונע מצב שמורה מקבל שתי קבוצות באותו יום ובאותה שעה,
        גם אם זה מקצוע אחר או שכבה אחרת.
    */
    $existingTeacherScheduleMap = [];

    if (count($teacherIds) > 0) {

        $placeholders = implode(",", array_fill(0, count($teacherIds), "?"));

        $teacherScheduleStmt = $pdo->prepare("
            SELECT 
                teacher_id,
                day_of_week,
                start_time
            FROM tutoring_groups
            WHERE teacher_id IN ($placeholders)
              AND status = 'approved'
        ");

        $teacherScheduleStmt->execute($teacherIds);
        $teacherScheduleRows = $teacherScheduleStmt->fetchAll(PDO::FETCH_ASSOC);

        $existingTeacherScheduleMap = buildExistingScheduleMap($teacherScheduleRows, "teacher_id");
    }

    $studentCount = count($students);
    $groupCount = calculateGroupCount($studentCount);

    /*
        ניסיון ראשון: GPT.
        אם GPT לא זמין או מחזיר משהו לא תקין, עוברים לשיבוץ פנימי.
        חשוב: GPT מחלק תלמידים ומורים, אבל את בדיקת השעות הסופית אנחנו עושים בקוד,
        כדי לא לסמוך עליו בעניין התנגשויות.
    */
    $groups = tryBuildGroupsWithGpt(
        $students,
        $teachers,
        $groupCount,
        $gradeLevel,
        $subjectName,
        $shuffleSeed
    );

    $algorithmSource = "gpt";
    $warnings = [];

    if (!$groups) {
        $algorithmSource = "internal";
        $warnings[] = "GPT לא החזיר שיבוץ תקין או שלא הוגדר מפתח OpenAI, ולכן הופעל שיבוץ חכם פנימי.";
        $groups = buildInternalSmartGroups($students, $teachers, $groupCount, $shuffleSeed);
    }

    /*
        שיבוץ יום ושעה לכל קבוצה לפי מגבלות:
        1. שעות רק בין 15:00 ל-19:00.
        2. תלמיד לא יכול להיות בשתי קבוצות באותו יום ובאותה שעה.
        3. תלמיד יכול להיות עד 2 תגבורים ביום.
        4. מורה לא יכול ללמד שתי קבוצות באותו יום ובאותה שעה.
    */
    $scheduleResult = assignSchedulesToGroups(
        $groups,
        $existingStudentScheduleMap,
        $existingTeacherScheduleMap,
        $shuffleSeed
    );

    if (!$scheduleResult["success"]) {
        echo json_encode([
            "success" => false,
            "message" => $scheduleResult["message"]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    calculateAverages($groups);

    echo json_encode([
        "success" => true,
        "grade_level" => $gradeLevel,
        "subject_id" => $subjectId,
        "subject_name" => $subjectName,
        "total_students" => $studentCount,
        "group_count" => $groupCount,
        "shuffle_seed" => $shuffleSeed,
        "algorithm_source" => $algorithmSource,
        "warnings" => $warnings,
        "groups" => $groups
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "שגיאה בשרת",
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>