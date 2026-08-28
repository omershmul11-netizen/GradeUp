<?php

ob_start();

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

function safe_json_exit($payload) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();

    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        safe_json_exit([
            "success" => false,
            "message" => "שגיאת שרת פנימית ביצירת המשימה. פרטי שגיאה: " . $error['message']
        ]);
    }
});

set_exception_handler(function ($e) {
    safe_json_exit([
        "success" => false,
        "message" => "שגיאת שרת פנימית ביצירת המשימה. פרטי שגיאה: " . $e->getMessage()
    ]);
});

require_once "../db_config.php";
require_once "../openai_config.php";

if (!defined('OPENAI_API_KEY') || trim(OPENAI_API_KEY) === '') {
    safe_json_exit([
        "success" => false,
        "message" => "מפתח OpenAI לא מוגדר בקובץ openai_config.php"
    ]);
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    safe_json_exit([
        "success" => false,
        "message" => "לא התקבלו נתונים"
    ]);
}

if (empty($data['group_id']) || empty($data['topic'])) {
    safe_json_exit([
        "success" => false,
        "message" => "חסר group_id או topic"
    ]);
}

$groupId = (int)$data['group_id'];
$topic = trim((string)$data['topic']);

function normalize_text($value) {
    $value = trim((string)$value);

    $value = str_replace(
        ['״', '”', '"', '׳', "'", '־', '–', '—', '_', '.', ',', ':', ';', '(', ')', '[', ']'],
        ['"', '"', '"', "'", "'", ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' '],
        $value
    );

    $value = preg_replace('/\s+/', ' ', $value);

    /*
        strtolower מטפל באנגלית.
        בעברית אין אותיות גדולות/קטנות ולכן אין צורך ב-mb_strtolower.
    */
    return strtolower(trim($value));
}

function contains_text($haystack, $needle) {
    $haystack = normalize_text($haystack);
    $needle = normalize_text($needle);

    if ($haystack === '' || $needle === '') {
        return false;
    }

    return strpos($haystack, $needle) !== false;
}

function subject_to_hebrew_name($subjectName) {
    $raw = trim((string)$subjectName);
    $normalized = normalize_text($raw);

    $map = [
        'math' => 'מתמטיקה',
        'mathematics' => 'מתמטיקה',
        'מתמטיקה' => 'מתמטיקה',

        'english' => 'אנגלית',
        'אנגלית' => 'אנגלית',

        'hebrew' => 'עברית',
        'עברית' => 'עברית',
        'לשון' => 'עברית',

        'computer science' => 'מדעי המחשב',
        'computerscience' => 'מדעי המחשב',
        'computer' => 'מדעי המחשב',
        'cs' => 'מדעי המחשב',
        'מדעי המחשב' => 'מדעי המחשב',
        'מדעי מחשב' => 'מדעי המחשב',

        'physics' => 'פיזיקה',
        'פיזיקה' => 'פיזיקה'
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    if (contains_text($normalized, 'computer') || contains_text($normalized, 'מדעי')) {
        return 'מדעי המחשב';
    }

    if (contains_text($normalized, 'math') || contains_text($normalized, 'מתמט')) {
        return 'מתמטיקה';
    }

    if (contains_text($normalized, 'english') || contains_text($normalized, 'אנגל')) {
        return 'אנגלית';
    }

    if (contains_text($normalized, 'hebrew') || contains_text($normalized, 'עברית') || contains_text($normalized, 'לשון')) {
        return 'עברית';
    }

    if (contains_text($normalized, 'physics') || contains_text($normalized, 'פיזיק')) {
        return 'פיזיקה';
    }

    return $raw;
}

function grade_to_hebrew_text($gradeLevel) {
    $grade = (int)$gradeLevel;

    if ($grade === 10) {
        return "כיתה י׳";
    }

    if ($grade === 11) {
        return "כיתה י״א";
    }

    if ($grade === 12) {
        return "כיתה י״ב";
    }

    return "תיכון";
}

function get_curriculum_topics($subjectHebrew, $gradeLevel) {
    $grade = (string)((int)$gradeLevel);

    $curriculum = [
        'מדעי המחשב' => [
            '10' => [
                'יסודות התכנות', 'יסודות השפה', 'מבוא לתכנות',
                'java', 'c#', 'שפת java', 'שפת c#',

                'משתנים', 'משתנה', 'סוגי נתונים', 'טיפוסי נתונים',
                'integer', 'int', 'double', 'string', 'מחרוזת', 'מחרוזות',
                'boolean', 'בוליאני', 'char', 'casting', 'המרות טיפוסים', 'המרת טיפוסים',

                'אופרטורים', 'אופרטורים חשבוניים', 'אופרטורים לוגיים',
                'פעולות חשבון', 'ביטויים חשבוניים', 'ביטויים לוגיים',

                'קלט ופלט', 'קלט', 'פלט', 'scanner', 'system out println',
                'console writeline', 'console readline', 'הדפסה למסך',

                'מבני בקרה', 'בקרה',
                'הסתעפויות', 'הסתעפות', 'תנאים', 'תנאי',
                'משפטי if', 'if', 'if else', 'else',
                'תנאים מורכבים', 'תנאי מורכב', 'תנאים מקוננים', 'תנאי מקונן',

                'לולאות', 'לולאה', 'מבני לולאה',
                'לולאת for', 'for', 'לולאות for', 'לולאות מונות', 'לולאה מונה',
                'לולאת while', 'while', 'לולאות while', 'לולאות תנאי',
                'תנאי עצירה', 'זקיף', 'לולאות זקיף',
                'לולאות מקוננות', 'לולאה מקוננת',

                'מערכים', 'מערך', 'מערכים חד מימדיים', 'מערכים חד-מימדיים',
                'מערך חד מימדי', 'מערך חד-מימדי',
                'הגדרת מערך', 'אתחול מערך', 'אינדקסים', 'אינדקס',
                'גישה לאינדקסים', 'סריקת מערך', 'סריקה של מערך',
                'מעבר על מערך', 'צבירת סכום', 'סכום במערך',
                'ממוצע במערך', 'מציאת מינימום', 'מציאת מקסימום',
                'מינימום במערך', 'מקסימום במערך', 'ערך קיצון',
                'מיקום במערך', 'מציאת מיקום',

                'מתודות', 'מתודה', 'פונקציות', 'פונקציה', 'פעולות', 'פעולה',
                'הגדרת פונקציה', 'הגדרת מתודה', 'חתימת מתודה',
                'פרמטרים', 'פרמטר', 'העברת פרמטרים', 'pass by value',
                'ערכי החזר', 'ערך החזר', 'return', 'void',
                'טווח משתנים', 'משתנים מקומיים', 'local', 'global'
            ],

            '11' => [
                'תכנות מונחה עצמים', 'מונחה עצמים', 'oop',
                'מחלקה', 'מחלקות', 'class',
                'אובייקט', 'אובייקטים', 'object',
                'תכונות', 'שדות', 'fields', 'attributes',
                'פעולות', 'שיטות', 'methods', 'מתודות',

                'פעולות בונות', 'בנאים', 'בנאי', 'constructors', 'constructor',
                'בנאי ברירת מחדל', 'בנאי מעתיק', 'copy constructor',
                'getters', 'setters', 'פעולות גישה', 'פעולות עדכון',
                'get', 'set', 'private', 'public', 'encapsulation', 'מיסוך', 'כימוס',

                'tostring', 'equals', 'השוואת אובייקטים',

                'קשרי גומלין', 'הכלה', 'אובייקט בתוך אובייקט',
                'composition', 'aggregation',
                'ירושה', 'inheritance', 'extends', 'מחלקת על', 'מחלקת בת',
                'super', 'דריסת פעולות', 'method overriding', 'overriding',
                'פולימורפיזם', 'polymorphism', 'רב צורתיות',
                'upcasting', 'downcasting', 'קישור דינמי',

                'מבני נתונים', 'מבנה נתונים',

                'מחסנית', 'stack', 'lifo',
                'push', 'pop', 'top', 'isempty',
                'מעקב מחסנית', 'היפוך מחסנית', 'סינון מחסנית',

                'תור ומערכים', 'תור', 'queue', 'fifo',
                'insert', 'remove', 'head',
                'הכנסה לתור', 'הוצאה מתור', 'שכפול תור', 'סיבוב תור',

                'רשימה מקושרת', 'linked list',
                'שרשרת חוליות', 'חוליות', 'חוליה', 'node',
                'צמתים', 'צומת', 'סריקת רשימה', 'סריקת רשימה מקושרת',
                'הוספת צומת', 'הוספת חוליה', 'מחיקת צומת', 'מחיקת חוליה',
                'הוספה בראש', 'הוספה באמצע', 'הוספה בסוף',

                'עצים בינאריים', 'עץ בינארי', 'binary tree',
                'bintreenode', 'שורש',
                'בן שמאלי', 'בן ימני', 'סריקות עצים',
                'in order', 'pre order', 'post order',
                'סריקה תוכית', 'סריקה תחילית', 'סריקה סופית',
                'עץ חיפוש בינארי', 'bst', 'binary search tree',

                'יעילות', 'סיבוכיות', 'אלגוריתמיקה', 'זמן ריצה',
                'מדידת זמן ריצה', 'o גדול', 'big o',
                'o 1', 'o logn', 'o log n', 'o n', 'o n 2',
                'השוואה בין אלגוריתמים',
                'חיפוש סדרתי', 'חיפוש לינארי', 'חיפוש בינארי',
                'מיון בועות', 'bubble sort',
                'מיון בחירה', 'selection sort',
                'מיון הכנסה', 'insertion sort',

                'רקורסיה', 'רקוריסיה', 'recursive', 'recursion',
                'קריאה עצמית', 'תנאי עצירה', 'base case',
                'שלב רקורסיבי', 'מחסנית קריאות', 'מעקב אחר מחסנית הקריאות',
                'עץ קריאות', 'עצרת', 'פיבונאצי', 'פיבונאצ׳י',
                'רקורסיה על מערכים', 'רקורסיה על רשימות', 'רקורסיה על עצים'
            ],

            '12' => [
                'מערכות הפעלה', 'אסמבלי', 'assembly',
                'מודלים חישוביים', 'מודל חישובי',
                'אוטומטים', 'אוטומט', 'אוטומט סופי',
                'אוטומט סופי דטרמיניסטי', 'dfa', 'אוסד', 'אוס"ד',
                'מצבים', 'מצב', 'מצב התחלתי', 'מצבים מקבלים',
                'פונקציית המעברים', 'מעברים',

                'שפות רגולריות', 'שפה רגולרית', 'regular languages',
                'בניית אוטומטים', 'שפות שמתקבלות על ידי אוטומט',

                'אוטומט סופי לא דטרמיניסטי', 'nfa', 'אסלד', 'אסל"ד',
                'המרת nfa ל dfa', 'המרת אסלד לאוסד',

                'דקדוקים', 'דקדוק', 'דקדוקים רגולריים',
                'דקדוקים חופשיי הקשר', 'cfg', 'context free grammar'
            ]
        ],

        'מתמטיקה' => [
            '10' => [
                'אחוזים', 'חישוב אחוזים', 'הנחות', 'התייקרויות', 'ריבית דריבית',
                'סטטיסטיקה', 'שכיחות', 'ממוצע', 'חציון', 'שכיח',
                'דיאגרמת עמודות', 'דיאגרמת עוגה', 'הסתברות בסיסית',
                'גיאומטריה אנליטית', 'מערכת צירים', 'מרחק בין שתי נקודות',
                'אמצע קטע', 'משוואת הישר', 'שיפוע', 'ישרים מקבילים',
                'טריגונומטריה בסיסית', 'משולש ישר זווית', 'סינוס', 'קוסינוס', 'טנגנס',
                'נוסחאות הכפל המקוצר', 'פירוק לגורמים', 'טרינום',
                'משוואות ממעלה שנייה', 'אי שוויונות'
            ],
            '11' => [
                'חזקות ושורשים', 'משוואות', 'נוסחת השורשים',
                'מערכת שתי משוואות', 'סדרה חשבונית', 'סכום סדרה',
                'מרובעים', 'טריגונומטריה', 'משפט הסינוסים',
                'דיאגרמת עץ', 'התפלגות נורמלית', 'הסתברות מותנית',
                'חדוא', 'פונקציות רציונליות', 'פונקציית שורש',
                'תחום הגדרה', 'אסימפטוטות', 'נקודות קיצון', 'אינטגרלים'
            ],
            '12' => [
                'פולינומים', 'גזירה', 'חקירת פונקציה', 'חקירת פונקציות',
                'שיפוע משיק', 'נקודות קיצון', 'עלייה וירידה', 'חיתוך עם הצירים',
                'בעיות קיצון', 'אינטגרלים', 'שטחים חסומים',
                'גידול ודעיכה', 'מודלים מעריכיים',
                'תיבה וקוביה', 'פירמידה', 'סדרות', 'טריגונומטריה במרחב',
                'פונקציות מעריכיות', 'פונקציות לוגריתמיות', 'ln', 'וקטורים',
                'מספרים מרוכבים', 'גיאומטריה אנליטית'
            ]
        ],

        'פיזיקה' => [
            '10' => [
                'אופטיקה', 'אופטיקה גיאומטרית', 'קרני האור', 'חוק החזרת האור',
                'חוק שבירת האור', 'חוק סנל', 'מקדם שבירה', 'זווית קריטית',
                'עדשות', 'נוסחת העדשות', 'קינמטיקה', 'מהירות ממוצעת',
                'מהירות רגעית', 'תנועה שוות מהירות'
            ],
            '11' => [
                'מכניקה', 'קינמטיקה', 'תנועה שוות תאוצה', 'נפילה חופשית',
                'זריקה אופקית', 'זריקה משופעת', 'דינמיקה', 'חוקי ניוטון',
                'דיאגרמת כוחות', 'חיכוך', 'גלגלות', 'תנועה מעגלית',
                'עבודה ואנרגיה', 'שימור אנרגיה', 'הספק',
                'מתקף ותנע', 'שימור תנע', 'כבידה', 'חוקי קפלר'
            ],
            '12' => [
                'חשמל', 'אלקטרוסטטיקה', 'מטען חשמלי', 'חוק קולון',
                'שדה חשמלי', 'פוטנציאל חשמלי', 'מעגלי זרם ישר',
                'זרם חשמלי', 'חוק אום', 'חיבור נגדים', 'נגדים בטור',
                'נגדים במקביל', 'הספק חשמלי', 'מגנטיות',
                'שדה מגנטי', 'כוח לורנץ', 'חוק פאראדיי', 'חוק לנץ',
                'קרינה וחומר', 'פוטונים', 'האפקט הפוטואלקטרי', 'מודל אטום המימן'
            ]
        ],

        'עברית' => [
            '10' => [
                'הבנת הנקרא', 'איתור מידע', 'פירוש מילים', 'ניבים',
                'פסקת טיעון', 'טענה', 'נימוק', 'דוגמה', 'סיכום בורר',
                'שורש', 'מוספיות', 'אותיות איתן', 'בניינים', 'גזרת השלמים'
            ],
            '11' => [
                'הבנת הנקרא', 'השוואה בין טקסטים', 'קהל יעד', 'עמדת הכותב',
                'עובדה ודעה', 'נימה', 'סקירה ממזגת',
                'תחביר', 'חלקי המשפט', 'נושא', 'נשוא', 'מושא',
                'תיאורים', 'לוואי', 'תמורה', 'משפט פשוט', 'משפט מאוחה',
                'משפט מורכב', 'פסוקית', 'המרות תחביריות',
                'מערכת הצורות', 'גזרות הפועל', 'שורש ומשקל', 'בסיס וצורן'
            ],
            '12' => [
                'הבנת הנקרא', 'השוואה בין טקסטים', 'קהל יעד', 'עמדת הכותב',
                'עובדה ודעה', 'נימה', 'סקירה ממזגת',
                'תחביר', 'חלקי המשפט', 'נושא', 'נשוא', 'מושא',
                'תיאורים', 'לוואי', 'תמורה', 'משפט פשוט', 'משפט מאוחה',
                'משפט מורכב', 'פסוקית', 'המרות תחביריות',
                'מערכת הצורות', 'גזרות הפועל', 'שורש ומשקל', 'בסיס וצורן'
            ]
        ],

        'אנגלית' => [
            '10' => [
                'grammar', 'דקדוק', 'tenses', 'present simple', 'present progressive',
                'past simple', 'past progressive', 'future simple',
                'adjectives', 'adverbs', 'vocabulary', 'basic writing',
                'subject verb object', 'capital letters'
            ],
            '11' => [
                'module e', 'reading comprehension', 'unseen', 'listening comprehension',
                'literature', 'count that day lost', 'a poison tree',
                'the split cherry tree', "thank you m'am",
                'hots', 'inferring', 'comparing and contrasting', 'cause and effect'
            ],
            '12' => [
                'module g', 'advanced reading', 'formal writing', 'opinion essay',
                'for and against essay', 'introduction', 'body paragraphs',
                'conclusion', 'connectors', 'oral exam', 'project presentation',
                'personal interview'
            ]
        ]
    ];

    if (!isset($curriculum[$subjectHebrew]) || !isset($curriculum[$subjectHebrew][$grade])) {
        return [];
    }

    /*
        במדעי המחשב חומר מתקדם נשען על חומר קודם:
        י"א כולל גם י', וי"ב כולל גם י' וגם י"א.
    */
    if ($subjectHebrew === 'מדעי המחשב') {
        if ($grade === '11') {
            return array_values(array_unique(array_merge(
                $curriculum[$subjectHebrew]['10'],
                $curriculum[$subjectHebrew]['11']
            )));
        }

        if ($grade === '12') {
            return array_values(array_unique(array_merge(
                $curriculum[$subjectHebrew]['10'],
                $curriculum[$subjectHebrew]['11'],
                $curriculum[$subjectHebrew]['12']
            )));
        }
    }

    return $curriculum[$subjectHebrew][$grade];
}

function topic_matches_allowed_topic($userTopic, $allowedTopic) {
    $userTopic = normalize_text($userTopic);
    $allowedTopic = normalize_text($allowedTopic);

    if ($userTopic === '' || $allowedTopic === '') {
        return false;
    }

    if (strpos($userTopic, $allowedTopic) !== false || strpos($allowedTopic, $userTopic) !== false) {
        return true;
    }

    $synonyms = [
        'לולאות' => ['לולאה', 'for', 'while'],
        'לולאה' => ['לולאות', 'for', 'while'],
        'תנאים' => ['תנאי', 'if', 'else'],
        'תנאי' => ['תנאים', 'if', 'else'],
        'מערכים' => ['מערך', 'array'],
        'מערך' => ['מערכים', 'array'],
        'פונקציות' => ['פונקציה', 'מתודות', 'מתודה', 'פעולות', 'פעולה', 'return'],
        'פונקציה' => ['פונקציות', 'מתודות', 'מתודה', 'פעולות', 'פעולה', 'return'],
        'מתודות' => ['מתודה', 'פונקציות', 'פונקציה', 'פעולות', 'פעולה'],
        'מחלקות' => ['מחלקה', 'class', 'oop'],
        'מחלקה' => ['מחלקות', 'class', 'oop'],
        'אובייקטים' => ['אובייקט', 'object', 'oop'],
        'אובייקט' => ['אובייקטים', 'object', 'oop'],
        'עצים' => ['עץ', 'עץ בינארי', 'binary tree'],
        'רשימות' => ['רשימה', 'רשימה מקושרת', 'linked list'],
        'רקורסיה' => ['רקוריסיה', 'recursion', 'recursive', 'קריאה עצמית'],
        'אוטומטים' => ['אוטומט', 'dfa', 'nfa'],
        'אוטומט' => ['אוטומטים', 'dfa', 'nfa']
    ];

    $userWords = preg_split('/\s+/', $userTopic);

    foreach ($userWords as $word) {
        $word = trim($word);

        if ($word === '') {
            continue;
        }

        if (strpos($allowedTopic, $word) !== false) {
            return true;
        }

        if (isset($synonyms[$word])) {
            foreach ($synonyms[$word] as $synonym) {
                $synonym = normalize_text($synonym);

                if (strpos($allowedTopic, $synonym) !== false || strpos($synonym, $allowedTopic) !== false) {
                    return true;
                }
            }
        }
    }

    return false;
}

function validate_topic_against_curriculum($subjectHebrew, $gradeLevel, $topic) {
    $topics = get_curriculum_topics($subjectHebrew, $gradeLevel);

    if (count($topics) === 0) {
        return [
            'success' => false,
            'matched_topic' => '',
            'message' => 'הנושא הנ"ל לא חלק מתוכנית הלימוד אנא הקלד נושא רלוונטי.'
        ];
    }

    foreach ($topics as $allowedTopic) {
        if (topic_matches_allowed_topic($topic, $allowedTopic)) {
            return [
                'success' => true,
                'matched_topic' => $allowedTopic,
                'message' => ''
            ];
        }
    }

    return [
        'success' => false,
        'matched_topic' => '',
        'message' => 'הנושא הנ"ל לא חלק מתוכנית הלימוד אנא הקלד נושא רלוונטי.'
    ];
}

function build_curriculum_topics_text($subjectHebrew, $gradeLevel) {
    $topics = get_curriculum_topics($subjectHebrew, $gradeLevel);

    if (count($topics) === 0) {
        return 'לא נמצאה תוכנית לימודים מוגדרת למקצוע ולשכבה.';
    }

    return implode(', ', array_slice(array_values(array_unique($topics)), 0, 80));
}

function normalize_correct_option($correctOption) {
    $correctOption = normalize_text($correctOption);

    $map = [
        'א' => 'a',
        'ב' => 'b',
        'ג' => 'c',
        'ד' => 'd',
        'option a' => 'a',
        'option b' => 'b',
        'option c' => 'c',
        'option d' => 'd'
    ];

    if (isset($map[$correctOption])) {
        return $map[$correctOption];
    }

    if (in_array($correctOption, ['a', 'b', 'c', 'd'], true)) {
        return $correctOption;
    }

    return '';
}

function post_to_openai($payload) {
    $url = "https://api.openai.com/v1/responses";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . trim(OPENAI_API_KEY)
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $response = curl_exec($ch);

        if ($response === false) {
            $curlError = curl_error($ch);
            curl_close($ch);

            return [
                'ok' => false,
                'http_code' => 0,
                'body' => null,
                'message' => "שגיאת cURL: " . $curlError
            ];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'ok' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'body' => $response,
            'message' => ''
        ];
    }

    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' =>
                    "Content-Type: application/json\r\n" .
                    "Authorization: Bearer " . trim(OPENAI_API_KEY) . "\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 45,
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        $httpCode = 0;

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                    $httpCode = (int)$matches[1];
                    break;
                }
            }
        }

        if ($response === false) {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'body' => null,
                'message' => "לא ניתן לשלוח בקשה ל-OpenAI דרך השרת."
            ];
        }

        return [
            'ok' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'body' => $response,
            'message' => ''
        ];
    }

    return [
        'ok' => false,
        'http_code' => 0,
        'body' => null,
        'message' => "בשרת לא פעיל cURL וגם allow_url_fopen כבוי."
    ];
}

function extract_openai_output_text($openaiResult) {
    if (isset($openaiResult['output_text'])) {
        return $openaiResult['output_text'];
    }

    if (isset($openaiResult['output']) && is_array($openaiResult['output'])) {
        foreach ($openaiResult['output'] as $outputItem) {
            if (!empty($outputItem['content']) && is_array($outputItem['content'])) {
                foreach ($outputItem['content'] as $contentItem) {
                    if (isset($contentItem['text'])) {
                        return $contentItem['text'];
                    }
                }
            }
        }
    }

    return null;
}

/*
    התחלת העבודה בפועל.
*/
$stmt = $pdo->prepare("
    SELECT 
        tg.group_id,
        tg.grade_level,
        s.subject_name,
        CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
    FROM tutoring_groups tg
    JOIN subjects s ON tg.subject_id = s.subject_id
    JOIN teachers t ON tg.teacher_id = t.teacher_id
    WHERE tg.group_id = :group_id
    LIMIT 1
");

$stmt->execute([
    ':group_id' => $groupId
]);

$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    safe_json_exit([
        "success" => false,
        "message" => "הקבוצה לא נמצאה"
    ]);
}

$gradeLevel = (int)$group['grade_level'];
$subjectHebrew = subject_to_hebrew_name($group['subject_name']);
$gradeText = grade_to_hebrew_text($gradeLevel);

$curriculumCheck = validate_topic_against_curriculum($subjectHebrew, $gradeLevel, $topic);

if (!$curriculumCheck['success']) {
    safe_json_exit([
        "success" => false,
        "message" => $curriculumCheck['message']
    ]);
}

$matchedTopic = $curriculumCheck['matched_topic'];
$curriculumTopicsText = build_curriculum_topics_text($subjectHebrew, $gradeLevel);

$subjectInstruction = "";

if ($subjectHebrew === 'מדעי המחשב') {
    $subjectInstruction = "
הנחיות למדעי המחשב:
- השאלות חייבות להיות יישומיות ולא שאלות הגדרה.
- רצוי לשלב קטע קוד קצר, מעקב אחר קוד, טבלת מעקב, מערך, לולאה, תנאי, מתודה, מחלקה או מבנה נתונים לפי הנושא.
- אם יש קוד, חובה להציג אותו בתוך question_text בצורה קריאה עם ירידות שורה.
- קוד חייב להיות באנגלית בלבד, למשל:

נתון הקוד הבא:

```text
sum = 0

for i from 1 to 5 do
    sum = sum + i
end for

print(sum)
```

מה יודפס בסוף הריצה?
";
} elseif ($subjectHebrew === 'מתמטיקה') {
    $subjectInstruction = "
הנחיות למתמטיקה:
- השאלות צריכות להיות בסגנון בגרות.
- חובה לכלול נתונים ממשיים, חישוב, ניתוח, גרף מילולי, פונקציה, משוואה או בעיה מילולית לפי הנושא.
- אין ליצור שאלות הגדרה בלבד.
";
} elseif ($subjectHebrew === 'פיזיקה') {
    $subjectInstruction = "
הנחיות לפיזיקה:
- כל שאלה צריכה לכלול מצב פיזיקלי אמיתי, נתונים מספריים או ניתוח קשר בין גדלים פיזיקליים.
- אין ליצור שאלות הגדרה בלבד.
";
} elseif ($subjectHebrew === 'עברית') {
    $subjectInstruction = "
הנחיות לעברית/לשון:
- יש לשלב משפט, קטע קצר, מילה לניתוח או דוגמה לשונית.
- השאלות צריכות לבדוק יישום ולא רק הגדרה.
";
} elseif ($subjectHebrew === 'אנגלית') {
    $subjectInstruction = "
הנחיות לאנגלית:
- יש לשלב משפטים או קטע קצר באנגלית.
- אפשר לבדוק grammar, vocabulary in context, reading comprehension או writing skills לפי הנושא.
";
}

$prompt = "
אתה יוצר דפי עבודה איכותיים לתלמידי תיכון בישראל.

נתוני המשימה:
- מקצוע: {$subjectHebrew}
- שכבה: {$gradeText}
- נושא שהמורה הקליד: {$topic}
- הנושא שאושר מתוך תוכנית הלימודים: {$matchedTopic}

תוכנית הלימודים המאושרת למקצוע ולשכבה:
{$curriculumTopicsText}

חובה:
- צור שאלות רק על הנושא שהמורה הקליד.
- אסור ליצור שאלות על נושא אחר.
- השאלות חייבות להתאים למקצוע {$subjectHebrew}.
- השאלות חייבות להיות ברמת תיכון ובסגנון הכנה לבגרות.
- אל תיצור שאלות הגדרה פשוטות.
- בכל שאלה יש תשובה נכונה אחת בלבד.
- correct_option חייב להיות רק a או b או c או d.
- ההסבר חייב להיות ברור וקצר.

{$subjectInstruction}

מבנה:
צור בדיוק 3 הצעות לדפי עבודה.
כל הצעה כוללת בדיוק 3 שאלות אמריקאיות.
הצעה 1: תרגול יסודי.
הצעה 2: תרגול יישומי.
הצעה 3: תרגול מאתגר.

החזר JSON בלבד, בלי Markdown ובלי טקסט נוסף.

מבנה JSON:
{
  \"suggestions\": [
    {
      \"title\": \"כותרת דף העבודה\",
      \"description\": \"תיאור קצר\",
      \"questions\": [
        {
          \"question_text\": \"נוסח השאלה\",
          \"option_a\": \"תשובה א\",
          \"option_b\": \"תשובה ב\",
          \"option_c\": \"תשובה ג\",
          \"option_d\": \"תשובה ד\",
          \"correct_option\": \"a\",
          \"explanation\": \"הסבר קצר וברור\"
        }
      ]
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
    "temperature" => 0.35,
    "max_output_tokens" => 6000
];

$apiResponse = post_to_openai($payload);

if (!$apiResponse['ok']) {
    $message = $apiResponse['message'];

    if ($message === '' && $apiResponse['body']) {
        $decodedError = json_decode($apiResponse['body'], true);

        if (isset($decodedError['error']['message'])) {
            $message = $decodedError['error']['message'];
        } else {
            $message = "שגיאת OpenAI. קוד HTTP: " . $apiResponse['http_code'];
        }
    }

    safe_json_exit([
        "success" => false,
        "message" => $message
    ]);
}

$openaiResult = json_decode($apiResponse['body'], true);

if (!$openaiResult) {
    safe_json_exit([
        "success" => false,
        "message" => "OpenAI החזיר תשובה לא תקינה."
    ]);
}

$outputText = extract_openai_output_text($openaiResult);

if (!$outputText) {
    safe_json_exit([
        "success" => false,
        "message" => "לא התקבל פלט טקסט תקין מהמודל."
    ]);
}

$suggestionsJson = json_decode($outputText, true);

if (!$suggestionsJson || empty($suggestionsJson['suggestions']) || !is_array($suggestionsJson['suggestions'])) {
    safe_json_exit([
        "success" => false,
        "message" => "המודל לא החזיר מבנה שאלות תקין."
    ]);
}

$cleanSuggestions = [];

foreach ($suggestionsJson['suggestions'] as $suggestion) {
    if (empty($suggestion['questions']) || !is_array($suggestion['questions'])) {
        continue;
    }

    $cleanQuestions = [];

    foreach ($suggestion['questions'] as $question) {
        $correctOption = normalize_correct_option(isset($question['correct_option']) ? $question['correct_option'] : '');

        if (
            empty($question['question_text']) ||
            empty($question['option_a']) ||
            empty($question['option_b']) ||
            empty($question['option_c']) ||
            empty($question['option_d']) ||
            !in_array($correctOption, ['a', 'b', 'c', 'd'], true)
        ) {
            continue;
        }

        $cleanQuestions[] = [
            "question_text" => trim((string)$question['question_text']),
            "option_a" => trim((string)$question['option_a']),
            "option_b" => trim((string)$question['option_b']),
            "option_c" => trim((string)$question['option_c']),
            "option_d" => trim((string)$question['option_d']),
            "correct_option" => $correctOption,
            "explanation" => trim(isset($question['explanation']) ? (string)$question['explanation'] : "הסבר לא סופק.")
        ];
    }

    if (count($cleanQuestions) >= 3) {
        $cleanSuggestions[] = [
            "title" => trim(isset($suggestion['title']) ? (string)$suggestion['title'] : "דף עבודה בנושא {$topic}"),
            "description" => trim(isset($suggestion['description']) ? (string)$suggestion['description'] : "דף עבודה בנושא {$topic}"),
            "questions" => array_slice($cleanQuestions, 0, 3)
        ];
    }
}

if (count($cleanSuggestions) === 0) {
    safe_json_exit([
        "success" => false,
        "message" => "המודל החזיר תשובה, אך לא נמצאו מספיק שאלות תקינות. נסה שוב או דייק את הנושא."
    ]);
}

safe_json_exit([
    "success" => true,
    "group" => [
        "group_id" => $groupId,
        "grade_level" => $gradeLevel,
        "subject_name" => $subjectHebrew,
        "topic" => $topic,
        "matched_curriculum_topic" => $matchedTopic
    ],
    "suggestions" => array_slice($cleanSuggestions, 0, 3)
]);
?>