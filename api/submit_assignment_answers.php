<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once "../db_config.php";

/*
    אבטחה:
    student_id נלקח רק מה-SESSION של התלמיד המחובר.
    לא מקבלים student_id מה-JavaScript כדי למנוע זיוף זהות.
*/
if (!isset($_SESSION['student_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "אין הרשאת תלמיד. יש להתחבר מחדש."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$studentId = (int)$_SESSION['student_id'];

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "לא התקבלו נתונים"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    empty($data['assignment_id']) ||
    empty($data['answers']) ||
    !is_array($data['answers'])
) {
    echo json_encode([
        "success" => false,
        "message" => "חסרים נתונים: משימה או תשובות"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$assignmentId = (int)$data['assignment_id'];
$answers = $data['answers'];

try {

    /*
        בדיקה שהתלמיד באמת שייך לקבוצה שקיבלה את המשימה.
        כך תלמיד לא יכול לענות על assignment_id שלא שייך אליו.
    */
    $checkAccess = $pdo->prepare("
        SELECT 
            a.assignment_id
        FROM assignments a
        JOIN tutoring_groups tg ON a.group_id = tg.group_id
        JOIN tutoring_group_students tgs ON tg.group_id = tgs.group_id
        WHERE a.assignment_id = :assignment_id
          AND tgs.student_id = :student_id
        LIMIT 1
    ");

    $checkAccess->execute([
        ':assignment_id' => $assignmentId,
        ':student_id' => $studentId
    ]);

    $access = $checkAccess->fetch(PDO::FETCH_ASSOC);

    if (!$access) {
        echo json_encode([
            "success" => false,
            "message" => "אין לך הרשאה להגיש משימה זו"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->beginTransaction();

    $totalQuestions = count($answers);
    $correctCount = 0;
    $feedbackList = [];

    if ($totalQuestions === 0) {
        throw new Exception("לא נשלחו תשובות לבדיקה");
    }

    /*
        שליפת תשובה נכונה והסבר מהשרת בלבד.
        התלמיד לא שולח correct_option ולא explanation.
    */
    $getQuestion = $pdo->prepare("
        SELECT 
            question_id,
            question_text,
            correct_option,
            explanation
        FROM assignment_questions
        WHERE question_id = :question_id
          AND assignment_id = :assignment_id
    ");

    $saveAnswer = $pdo->prepare("
        INSERT INTO assignment_answers
        (
            assignment_id,
            question_id,
            student_id,
            selected_option,
            is_correct,
            feedback
        )
        VALUES
        (
            :assignment_id,
            :question_id,
            :student_id,
            :selected_option,
            :is_correct,
            :feedback
        )
        ON DUPLICATE KEY UPDATE
            selected_option = VALUES(selected_option),
            is_correct = VALUES(is_correct),
            feedback = VALUES(feedback),
            answered_at = CURRENT_TIMESTAMP
    ");

    foreach ($answers as $answer) {

        if (
            empty($answer['question_id']) ||
            empty($answer['selected_option'])
        ) {
            throw new Exception("אחת התשובות חסרה question_id או selected_option");
        }

        $questionId = (int)$answer['question_id'];
        $selectedOption = strtolower(trim($answer['selected_option']));

        if (!in_array($selectedOption, ['a', 'b', 'c', 'd'])) {
            throw new Exception("תשובה לא חוקית נשלחה");
        }

        $getQuestion->execute([
            ':question_id' => $questionId,
            ':assignment_id' => $assignmentId
        ]);

        $question = $getQuestion->fetch(PDO::FETCH_ASSOC);

        if (!$question) {
            throw new Exception("שאלה לא נמצאה: " . $questionId);
        }

        $correctOption = strtolower(trim($question['correct_option']));
        $isCorrect = ($selectedOption === $correctOption) ? 1 : 0;

        if ($isCorrect) {
            $correctCount++;

            $feedback = "תשובה נכונה ✅ " . $question['explanation'];
        } else {
            $feedback = "תשובה לא נכונה ❌ התשובה הנכונה היא: " .
                        optionToHebrew($correctOption) .
                        ". " .
                        $question['explanation'];
        }

        $saveAnswer->execute([
            ':assignment_id' => $assignmentId,
            ':question_id' => $questionId,
            ':student_id' => $studentId,
            ':selected_option' => $selectedOption,
            ':is_correct' => $isCorrect,
            ':feedback' => $feedback
        ]);

        $feedbackList[] = [
            "question_id" => $questionId,
            "question_text" => $question['question_text'],
            "selected_option" => $selectedOption,
            "correct_option" => $correctOption,
            "is_correct" => $isCorrect,
            "feedback" => $feedback
        ];
    }

    $score = round(($correctCount / $totalQuestions) * 100);

    $generalFeedback = "הציון שלך הוא {$score}. ענית נכון על {$correctCount} מתוך {$totalQuestions} שאלות.";

    $saveSubmission = $pdo->prepare("
        INSERT INTO assignment_submissions
        (
            assignment_id,
            student_id,
            answer_text,
            ai_feedback,
            ai_score,
            status
        )
        VALUES
        (
            :assignment_id,
            :student_id,
            :answer_text,
            :ai_feedback,
            :ai_score,
            'checked_by_ai'
        )
        ON DUPLICATE KEY UPDATE
            answer_text = VALUES(answer_text),
            ai_feedback = VALUES(ai_feedback),
            ai_score = VALUES(ai_score),
            status = 'checked_by_ai',
            updated_at = CURRENT_TIMESTAMP
    ");

    $saveSubmission->execute([
        ':assignment_id' => $assignmentId,
        ':student_id' => $studentId,
        ':answer_text' => json_encode($answers, JSON_UNESCAPED_UNICODE),
        ':ai_feedback' => $generalFeedback,
        ':ai_score' => $score
    ]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "התשובות נבדקו ונשמרו בהצלחה",
        "score" => $score,
        "correct_count" => $correctCount,
        "total_questions" => $totalQuestions,
        "general_feedback" => $generalFeedback,
        "feedback" => $feedbackList
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

function optionToHebrew($option) {
    if ($option === 'a') return 'א';
    if ($option === 'b') return 'ב';
    if ($option === 'c') return 'ג';
    if ($option === 'd') return 'ד';

    return $option;
}
?>