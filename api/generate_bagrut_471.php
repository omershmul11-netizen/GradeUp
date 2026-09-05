<?php

session_start();
ob_start();

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
set_time_limit(240);
error_reporting(E_ALL);

function bagrut_json_exit($payload, $status = 200) {
    http_response_code($status);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_exception_handler(function ($exception) {
    bagrut_json_exit([
        'success' => false,
        'message' => 'יצירת שאלת הבגרות נכשלה. נסו שוב בעוד רגע.'
    ], 500);
});

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../openai_config.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../bagrut471/curriculum_catalog.php';

if (empty($_SESSION['teacher_id'])) {
    bagrut_json_exit(['success' => false, 'message' => 'יש להתחבר כמורה.'], 401);
}

if (!defined('OPENAI_API_KEY') || trim(OPENAI_API_KEY) === '') {
    bagrut_json_exit(['success' => false, 'message' => 'מפתח OpenAI אינו מוגדר.'], 503);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    bagrut_json_exit(['success' => false, 'message' => 'לא התקבלו נתונים תקינים.'], 400);
}

$groupId = (int)($data['group_id'] ?? 0);
$topicId = trim((string)($data['curriculum_topic_id'] ?? ''));
$subtopicId = trim((string)($data['curriculum_subtopic_id'] ?? ''));

if ($groupId <= 0) {
    bagrut_json_exit(['success' => false, 'message' => 'יש לבחור קבוצת מתמטיקה.'], 422);
}

$groupStatement = $pdo->prepare(
    'SELECT tg.group_id, tg.grade_level, tg.study_units, s.subject_name
     FROM tutoring_groups tg
     JOIN subjects s ON s.subject_id = tg.subject_id
     WHERE tg.group_id = :group_id AND tg.teacher_id = :teacher_id
     LIMIT 1'
);
$groupStatement->execute([
    ':group_id' => $groupId,
    ':teacher_id' => (int)$_SESSION['teacher_id']
]);
$group = $groupStatement->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    bagrut_json_exit(['success' => false, 'message' => 'הקבוצה אינה משויכת למורה המחובר.'], 403);
}

$subject = trim((string)$group['subject_name']);
$isMath = stripos($subject, 'math') !== false || strpos($subject, 'מתמט') !== false;
if (!$isMath || (int)$group['grade_level'] !== 11 || (int)$group['study_units'] !== 4) {
    bagrut_json_exit([
        'success' => false,
        'message' => 'מחולל 471 זמין לקבוצת מתמטיקה בכיתה י״א, 4 יח״ל.'
    ], 422);
}

$catalog = gradeup_curriculum_for_group($subject, (int)$group['grade_level'], (int)$group['study_units']);
$selection = $catalog ? gradeup_curriculum_selection($catalog, $topicId, $subtopicId) : null;
if (!$selection) {
    bagrut_json_exit([
        'success' => false,
        'message' => 'יש לבחור נושא ותת־נושא מתוך תוכנית י״א 4 יח״ל.'
    ], 422);
}

function bagrut_post_openai($payload) {
    $handle = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim(OPENAI_API_KEY)
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
    $body = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    if ($body === false) {
        return ['ok' => false, 'message' => 'שגיאת תקשורת עם OpenAI: ' . $error];
    }
    $decoded = json_decode($body, true);
    if ($status < 200 || $status >= 300) {
        $message = $decoded['error']['message'] ?? ('OpenAI HTTP ' . $status);
        return ['ok' => false, 'message' => $message];
    }
    return ['ok' => true, 'body' => $decoded];
}

function bagrut_extract_output_text($response) {
    if (!empty($response['output_text'])) {
        return (string)$response['output_text'];
    }
    foreach (($response['output'] ?? []) as $item) {
        foreach (($item['content'] ?? []) as $content) {
            if (isset($content['text'])) {
                return (string)$content['text'];
            }
        }
    }
    return '';
}

function bagrut_load_skill($topicId) {
    $base = __DIR__ . '/../bagrut471/skill/';
    $files = $topicId === 'calculus'
        ? ['SKILL.md', 'content-standard.md', 'visual-standard.md']
        : ['visual-standard.md'];
    $content = '';
    foreach ($files as $file) {
        $path = $base . $file;
        if (!is_file($path)) {
            throw new RuntimeException('Skill resource is missing: ' . $file);
        }
        $content .= "\n\n--- {$file} ---\n" . file_get_contents($path);
    }
    return $content;
}

function bagrut_build_prompt($skillText, $selection, $previousError = '') {
    $retryText = $previousError === '' ? '' : "\nהפקה קודמת נכשלה בבדיקת המבנה. תקן במיוחד: {$previousError}\n";
    $topic = $selection['topic_name'];
    $subtopic = $selection['subtopic_name'];
    $topicId = $selection['topic_id'];
    $hours = (int)($selection['hours'] ?? 0);
    $domainRules = [
        'statistics-probability' => 'השתמש רק בתכני החוברת: ממוצע וסטיית תקן, ציוני תקן, התפלגות נורמלית והטבלה המצטברת, קשר בין משתנים, דיאגרמת פיזור, מקדם מתאם וישר רגרסיה. אל תכניס הסתברות מותנית או דיאגרמת עץ כנושא עצמאי. מותר להשתמש בטבלת נתונים באמצעות data_table.',
        'geometry' => 'השתמש רק בתכני החוברת: מעגל, מיתרים וקשתות, זוויות מרכזיות והיקפיות, משיקים, משולש ומרובע חסומים, משולש חוסם מעגל, משפט הסינוסים, שטחים, משוואת מעגל, מצב הדדי בין מעגל לישר ומשיק אנליטי. שלב בין אוקלידית, אנליטית וטריגונומטריה כשזה מתאים. אל תפנה לסרטוט שאינו מופיע בדף.',
        'calculus' => 'השתמש רק בתכני החוברת: קדם־אנליזה, פונקציות רציונליות ושורש, נגזרות, משיקים, אסימפטוטות, נקודות קיצון, זוגיות, קשר פונקציה־נגזרת, פרמטר יחיד, שאלות קיצון, פונקציה קדומה, אינטגרל מסוים ושטחים. נגזרת שנייה מחוץ לתוכנית ואסורה.',
    ][$topicId];
    if (($selection['generation_mode'] ?? 'practice_worksheet') !== 'summary_question') {
        return <<<PROMPT
אתה מחולל דפי העבודה הרשמי של GradeUp. פעל לפי תקני התוכן והעיצוב המצורפים.

{$skillText}

בקשה:
- צור דף עבודה מדורג אחד לתת־הנושא: {$subtopic}.
- הנושא הראשי: {$topic}. הקצאת ההוראה לתת־הנושא: {$hours} שעות.
- יעד: 4 יחידות, כיתה י״א.
- זהו דף תרגול של מיומנות, לא שאלת בגרות מלאה ולא חקירה אחת ארוכה.
- בנה חוברת תרגול בת שישה עמודי A4, במבנה המדויק של דף הייחוס המאושר של GradeUp.
- החוברת כוללת 34 תרגילים/סעיפים קצרים ומגוונים בארבעה מקטעים: 10 בסיס, 10 תרגול רגיל, 8 מתקדמים ו־6 סעיפי יישום.
- כל תרגיל יכוון למיומנות ברורה מתוך תת־הנושא. העלייה בקושי תהיה הדרגתית.
- מקטע היישום הוא רצף קצר ותלוי־הקשר שמיישם רק את תת־הנושא שנבחר. הוא אינו שאלת בגרות מלאה, אינו חקירת פונקציה מלאה ואינו בודק את כל הנושא הראשי.
- אין שאלות אמריקאיות, אין שלוש חלופות ואין פתרונות בדף התלמיד.
- הקצה 1–3 שורות עבודה לכל תרגיל לפי אורך הפתרון הצפוי.
- המספור מתחיל מחדש בכל מקטע. כל מספר תרגיל יוצג משמאל לתרגיל ובכיוון LTR; המלל העברי נשאר RTL והמתמטיקה LTR.
- פצל את המקטעים לשישה עמודים בדיוק: בסיס בשני עמודים (8+2), תרגול בשני עמודים (8+2), מתקדם בעמוד אחד (8), יישום בעמוד אחד (6).
- {$domainRules}
- בטקסט הגלוי השתמש בסימון מתמטי מודפס בלבד: √ ולא sqrt, וחזקות רגילות ולא **.
- בצע פתרון מלא ובדיקת נכונות באופן פרטי בשדה private_validation בלבד.
{$retryText}

החזר JSON בלבד. אין Markdown.

המבנה המחייב:
{
  "questions": [
    {
      "id": "gradeup-worksheet-unique-latin-id",
      "document_type": "practice_worksheet",
      "title": "{$subtopic}",
      "topic_label": "{$topic}",
      "unit_label": "י״א | 4 יח״ל",
      "difficulty_label": "מדורג: בסיס עד יישום",
      "instructions": "פתרו לפי הסדר והציגו דרך.",
      "sections": [
        {
          "title": "חלק א — רמת בסיס: זיהוי והבנה",
          "pages": [
            {
              "instruction": "חימום וזיהוי המיומנות",
              "exercises": [
                {
                  "number": 1,
                  "parts": [{"kind":"he","text":"הוראה קצרה"}],
                  "formula": FORMULA_NODE או null,
                  "workspace_lines": 1,
                  "answer_label": "פתרון:"
                }
              ]
            }
          ]
        }
      ],
      "private_validation": {
        "skill_map": "המיומנות המדויקת שכל תרגיל משרת",
        "solution_outline": "פתרון פרטי מלא לכל התרגילים",
        "final_checks": "אימות הדרגתיות, נכונות והתאמה לתת־הנושא"
      }
    }
  ]
}

חובה להחזיר בדיוק ארבעה sections ובסדר הבא:
1. חלק א — רמת בסיס: זיהוי והבנה — שני pages עם 8 ואז 2 תרגילים
2. חלק ב — תרגול רגיל — שני pages עם 8 ואז 2 תרגילים
3. חלק ג — רמה מתקדמת: נימוק ואיתור טעות — page אחד עם 8 תרגילים
4. חלק ד — יישום בסגנון בגרות — page אחד עם 6 סעיפים קצרים סביב הקשר משותף

בכל section המספור מתחיל ב־1 ורציף עד מספר התרגילים במקטע. בכל page השני של אותו section המשך את המספור מן העמוד הקודם.
במקטע ד מותר להוסיף לשדה section את context_parts ואת context_formula להצגת נתון משותף לפני ששת הסעיפים.

FORMULA_NODE הוא אחד מהבאים בלבד:
- {"type":"text","value":"f(x)=..."}
- {"type":"row","children":[FORMULA_NODE,...]}
- {"type":"sup","base":FORMULA_NODE,"exponent":FORMULA_NODE}
- {"type":"frac","numerator":FORMULA_NODE,"denominator":FORMULA_NODE}
- {"type":"sqrt","value":FORMULA_NODE}

בנה נוסחאות באמצעות row, frac, sup ו-sqrt. חלק עברי מקבל kind=he ומתמטיקה מקבלת kind=math.
PROMPT;
    }

    return <<<PROMPT
אתה מחולל דפי העבודה הרשמי של GradeUp. פעל לפי תקני התוכן והעיצוב המצורפים.

{$skillText}

בקשה:
- צור שאלת סיכום מלאה אחת בלבד בנושא הראשי: {$topic}.
- תת־הנושא המחייב הוא שאלת הסיכום: {$subtopic}.
- יעד: 4 יחידות, כיתה י״א, רמת בגרות.
- הדף מכיל שאלה פתוחה אחת, קוהרנטית ומדורגת, עם סעיפים. אין שאלות אמריקאיות ואין חלופות לבחירה.
- הדף נכנס לעמוד A4 יחיד ואינו כולל פתרון או תשובות.
- {$domainRules}
- אין להציג פתרון או תשובות בתוך השאלה.
- בחדו״א ניתן להשתמש ב-formula. בסטטיסטיקה ובגאומטריה השתמש ב-intro_parts והשאר את formula כ-null.
- בטקסט הגלוי השתמש בסימון מתמטי מודפס בלבד: √ ולא sqrt, וחזקות רגילות ולא **.
- בצע פתרון מלא ובדיקת נכונות באופן פרטי בשדה private_validation בלבד.
{$retryText}

החזר JSON בלבד. אין Markdown.

המבנה המחייב:
{
  "questions": [
    {
      "id": "gradeup-worksheet-unique-latin-id",
      "document_type": "summary_question",
      "title": "שאלת סיכום — {$subtopic}",
      "question_number": 1,
      "formula": FORMULA_NODE או null,
      "intro_parts": [{"kind":"he","text":"..."},{"kind":"math","text":"..."}],
      "elements": [LAYOUT_ELEMENT, ...],
      "private_validation": {
        "solution_outline": "פתרון פרטי מלא לכל הסעיפים",
        "curriculum_check": "אימות התאמה לתת־הנושא ולחוברת",
        "final_checks": "תיאור מפורש שכל הסעיפים נבדקו"
      }
    }
  ]
}

FORMULA_NODE הוא אחד מהבאים בלבד:
- {"type":"text","value":"f(x)=..."}
- {"type":"row","children":[FORMULA_NODE,...]}
- {"type":"sup","base":FORMULA_NODE,"exponent":FORMULA_NODE}
- {"type":"frac","numerator":FORMULA_NODE,"denominator":FORMULA_NODE}
- {"type":"sqrt","value":FORMULA_NODE}

בנה את הנוסחה הראשית באמצעות row, frac, sup ו-sqrt. טקסט מתמטי בתוך value יהיה קצר, למשל x, −6x+a, (x−2), 2.

LAYOUT_ELEMENT הוא אחד מאלה:
1. פסקה:
{"type":"paragraph","parts":[{"kind":"he","text":"..."},{"kind":"math","text":"..."}],"bold":false,"after":4}
2. סעיף:
{"type":"item","label":"א." או "ג. (1)" או "(2)","parts":[...],"after":3}
3. טבלת נתונים, לפי הצורך:
{"type":"data_table","headers":["x","y"],"rows":[["1","4"],["2","7"]],"after":5}
4. ארבעה גרפים, רק בחדו״א ורק אם נדרש באמת:
{"type":"graph_choices","xlim":[0,28],"ylim":[-6,2],"vertical_asymptotes":[1],"choices":[
  {"label":"I","expression":"(x-5)/(x-1)**1.5"},
  {"label":"II","expression":"-(x-5)/(x-1)**1.5"},
  {"label":"III","expression":"-1/sqrt(x-1)"},
  {"label":"IV","expression":"(x-9)/(x-1)**1.5"}
]}

ביטויי גרף רשאים להשתמש רק ב-x, מספרים, +, -, *, /, **, sqrt ו-abs. סדר הגרפים החזותי מטופל אוטומטית.

כללי מבנה קשיחים:
- 4 עד 20 elements בדף.
- label חייב להיות אות עברית ונקודה, או אות ותת-סעיף, או תת-סעיף בלבד.
- חלק עברי מקבל kind=he ומתמטיקה מקבלת kind=math.
- אל תכניס תוצאות מתוך private_validation אל elements.
- נוסח השאלה צריך להיות טבעי, מדורג וברמת הדוגמאות שבחוברת תוכנית הלימודים.
PROMPT;
}

function bagrut_visible_payload($payload, $expectedCount) {
    if (!is_array($payload) || !isset($payload['questions']) || !is_array($payload['questions'])) {
        throw new RuntimeException('המודל לא החזיר מערך questions.');
    }
    if (count($payload['questions']) !== $expectedCount) {
        throw new RuntimeException('מספר השאלות שהוחזר אינו תואם לבקשה.');
    }
    foreach ($payload['questions'] as &$question) {
        if (empty($question['private_validation'])) {
            throw new RuntimeException('חסרה בדיקה פרטית.');
        }
        $documentType = (string)($question['document_type'] ?? 'summary_question');
        if ($documentType === 'practice_worksheet') {
            $sections = $question['sections'] ?? null;
            if (!is_array($sections) || count($sections) !== 4) {
                throw new RuntimeException('דף עבודה חייב להכיל ארבעה מקטעים מדורגים.');
            }
            $expectedTitles = [
                'חלק א — רמת בסיס: זיהוי והבנה',
                'חלק ב — תרגול רגיל',
                'חלק ג — רמה מתקדמת: נימוק ואיתור טעות',
                'חלק ד — יישום בסגנון בגרות'
            ];
            $expectedPageCounts = [2, 2, 1, 1];
            $expectedExerciseCounts = [[8, 2], [8, 2], [8], [6]];
            $exerciseCount = 0;
            foreach ($sections as $index => $section) {
                if (($section['title'] ?? '') !== $expectedTitles[$index]) {
                    throw new RuntimeException('כותרות מקטעי דף העבודה אינן במבנה המאושר.');
                }
                $pages = $section['pages'] ?? null;
                if (!is_array($pages) || count($pages) !== $expectedPageCounts[$index]) {
                    throw new RuntimeException('חלוקת העמודים בדף העבודה אינה במבנה המאושר.');
                }
                $sectionExerciseNumber = 0;
                foreach ($pages as $pageIndex => $page) {
                    $exercises = $page['exercises'] ?? null;
                    if (!is_array($exercises) || count($exercises) !== $expectedExerciseCounts[$index][$pageIndex]) {
                        throw new RuntimeException('מספר התרגילים בעמוד אינו תואם לדף העבודה המאושר.');
                    }
                    foreach ($exercises as $exercise) {
                        $exerciseCount++;
                        $sectionExerciseNumber++;
                        if ((int)($exercise['number'] ?? 0) !== $sectionExerciseNumber) {
                            throw new RuntimeException('מספור התרגילים במקטע אינו רציף.');
                        }
                        $lines = (int)($exercise['workspace_lines'] ?? 0);
                        if ($lines < 1 || $lines > 3) {
                            throw new RuntimeException('שטח העבודה לתרגיל אינו בטווח המאושר.');
                        }
                        if (empty($exercise['parts']) && empty($exercise['formula'])) {
                            throw new RuntimeException('נמצא תרגיל ללא תוכן.');
                        }
                    }
                }
            }
            if ($exerciseCount !== 34) {
                throw new RuntimeException('דף עבודה חייב להכיל 34 תרגילים וסעיפים מדורגים.');
            }
            $visible = json_encode($sections, JSON_UNESCAPED_UNICODE);
            foreach (['פתרון מלא', 'תשובה סופית', 'private_validation'] as $forbidden) {
                if (strpos($visible, $forbidden) !== false) {
                    throw new RuntimeException('נמצא טקסט פתרון באזור הגלוי.');
                }
            }
            foreach (['sqrt(', '**', 'FORMULA_NODE', 'private_validation'] as $technicalNotation) {
                if (strpos($visible, $technicalNotation) !== false) {
                    throw new RuntimeException('נמצא סימון טכני שאינו מתאים לדף עבודה: ' . $technicalNotation);
                }
            }
            unset($question['private_validation']);
            continue;
        }
        if ($documentType !== 'summary_question') {
            throw new RuntimeException('סוג המסמך אינו נתמך.');
        }
        if (empty($question['elements'])) {
            throw new RuntimeException('חסרים סעיפים או בדיקה פרטית.');
        }
        if (empty($question['formula']) && empty($question['intro_parts'])) {
            throw new RuntimeException('חסרה פתיחה לדף העבודה.');
        }
        $visible = json_encode($question['elements'], JSON_UNESCAPED_UNICODE);
        foreach (['פתרון', 'תשובה סופית', 'private_validation'] as $forbidden) {
            if (strpos($visible, $forbidden) !== false) {
                throw new RuntimeException('נמצא טקסט פתרון באזור הגלוי.');
            }
        }
        foreach (['sqrt(', '**', 'FORMULA_NODE', 'private_validation'] as $technicalNotation) {
            if (strpos($visible, $technicalNotation) !== false) {
                throw new RuntimeException('נמצא סימון טכני שאינו מתאים לדף בגרות: ' . $technicalNotation);
            }
        }
        $elementCount = count($question['elements']);
        if ($elementCount < 4 || $elementCount > 20) {
            throw new RuntimeException('מספר רכיבי השאלה אינו בטווח המאושר.');
        }
        foreach ($question['elements'] as $element) {
            if (($element['type'] ?? '') !== 'item') {
                continue;
            }
            $label = trim((string)($element['label'] ?? ''));
            if (!preg_match('/^(?:[א-ת]\.\s*(?:\([1-9]\))?|\([1-9]\))$/u', $label)) {
                throw new RuntimeException('תווית סעיף אינה בפורמט הבגרות: ' . $label);
            }
        }
        unset($question['private_validation']);
    }
    unset($question);
    return ['questions' => $payload['questions']];
}

function bagrut_cleanup_old_files($root) {
    if (!is_dir($root)) {
        return;
    }
    $cutoff = time() - 86400;
    foreach (new DirectoryIterator($root) as $entry) {
        if ($entry->isDot() || !$entry->isDir() || $entry->isLink()) {
            continue;
        }
        if ($entry->getMTime() >= $cutoff) {
            continue;
        }
        $directory = $entry->getPathname();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}

$skillText = bagrut_load_skill($topicId);
$model = (string)gradeup_config('OPENAI_BAGRUT_MODEL', 'gpt-5.4');
$generatedRoot = realpath(__DIR__ . '/..') . '/generated/bagrut-471';
if (!is_dir($generatedRoot) && !mkdir($generatedRoot, 0775, true) && !is_dir($generatedRoot)) {
    bagrut_json_exit(['success' => false, 'message' => 'לא ניתן ליצור תיקיית פלט.'], 500);
}
bagrut_cleanup_old_files($generatedRoot);

$lastError = '';
for ($attempt = 1; $attempt <= 2; $attempt++) {
    $prompt = bagrut_build_prompt($skillText, $selection, $lastError);
    $apiResult = bagrut_post_openai([
        'model' => $model,
        'reasoning' => ['effort' => 'high'],
        'input' => [[
            'role' => 'user',
            'content' => [['type' => 'input_text', 'text' => $prompt]]
        ]],
        'text' => ['format' => ['type' => 'json_object']],
        'max_output_tokens' => 18000
    ]);
    if (!$apiResult['ok']) {
        $lastError = $apiResult['message'];
        continue;
    }
    $outputText = bagrut_extract_output_text($apiResult['body']);
    $decoded = json_decode($outputText, true);
    if (!is_array($decoded)) {
        $lastError = 'JSON לא תקין';
        continue;
    }
    try {
        $visiblePayload = bagrut_visible_payload($decoded, 1);
        $requestToken = bin2hex(random_bytes(12));
        $outputDirectory = $generatedRoot . '/' . $requestToken;
        if (!mkdir($outputDirectory, 0775, true)) {
            throw new RuntimeException('לא ניתן ליצור תיקיית בקשה.');
        }
        $specPath = tempnam(sys_get_temp_dir(), 'gradeup-471-');
        file_put_contents($specPath, json_encode($visiblePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $renderer = realpath(__DIR__ . '/../bagrut471/render_question.py');
        $command = 'python3 ' . escapeshellarg($renderer) . ' ' . escapeshellarg($specPath) . ' ' . escapeshellarg($outputDirectory) . ' 2>&1';
        $lines = [];
        $exitCode = 0;
        exec($command, $lines, $exitCode);
        unlink($specPath);
        if ($exitCode !== 0) {
            throw new RuntimeException(implode("\n", array_slice($lines, -8)));
        }
        $rendererResult = json_decode(end($lines), true);
        if (empty($rendererResult['success']) || empty($rendererResult['files'])) {
            throw new RuntimeException('מנוע ה-PDF לא החזיר קבצים.');
        }
        $files = [];
        foreach ($rendererResult['files'] as $file) {
            $files[] = [
                'title' => $file['title'],
                'pdf_url' => 'generated/bagrut-471/' . $requestToken . '/' . rawurlencode($file['pdf']),
                'preview_url' => 'generated/bagrut-471/' . $requestToken . '/' . str_replace('%2F', '/', rawurlencode($file['preview']))
            ];
        }
        $file = $files[0];
        bagrut_json_exit([
            'success' => true,
            'model' => $model,
            'curriculum_selection' => $selection,
            'file' => $file,
            'files' => [$file]
        ]);
    } catch (Throwable $error) {
        $lastError = substr($error->getMessage(), 0, 800);
    }
}

bagrut_json_exit([
    'success' => false,
    'message' => 'לא הצלחנו להפיק PDF שעבר את כל בדיקות האיכות. נסו שוב.',
    'details' => $lastError
], 502);
