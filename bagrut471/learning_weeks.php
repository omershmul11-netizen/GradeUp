<?php

/**
 * Canonical GradeUp 4-unit Grade 11 learning-week catalog.
 * IDs are stable: generated content and assignment metadata may rely on them.
 */
function gradeup_471_learning_weeks(): array
{
    return [
        'w01-investigation-foundations' => [
            'number' => 1,
            'display_name' => 'שער החקירה',
            'generator_focus' => 'תחום, חיתוכים, חיוביות/שליליות, זוגיות וקריאת מבנה הפונקציה. הימנעו מפרמטר מורכב ומחקירה מלאה ארוכה.'
        ],
        'w02-transformations-and-tangent' => [
            'number' => 2,
            'display_name' => 'טרנספורמציות ומשיק',
            'generator_focus' => 'הזזה וכיווץ של גרף, שיפוע משיק ונגזרת בסיסית. דרשו העברת תכונות ללא גזירה מחדש.'
        ],
        'w03-product-and-quotient-rules' => [
            'number' => 3,
            'display_name' => 'כללי גזירה ופישוט',
            'generator_focus' => 'כלל המכפלה וכלל המנה, פישוט ופירוק מונה הנגזרת. אל תדרשו חקירה מלאה.'
        ],
        'w04-derivative-sign-and-extrema' => [
            'number' => 4,
            'display_name' => 'טבלת נגזרת ונקודות קיצון',
            'generator_focus' => 'נגזרת שורש, טבלת סימנים, תחומי עלייה/ירידה ונקודות קיצון, כולל קצה תחום כשמתאים.'
        ],
        'w05-rational-asymptotes' => [
            'number' => 5,
            'display_name' => 'אסימפטוטות וחקירה רציונלית',
            'generator_focus' => 'חורים, אסימפטוטות והתנהגות ליד גבול תחום, ורכיבי חקירה רציונלית בלי פרמטר מורכב.'
        ],
        'w06-full-rational-investigation' => [
            'number' => 6,
            'display_name' => 'חקירה רציונלית מלאה',
            'generator_focus' => 'שאלת חקירה רציונלית מלאה עם פרמטר משמעותי, סקיצה וסעיפי חקירה מקושרים.'
        ],
        'w07-radical-domain-and-extrema' => [
            'number' => 7,
            'display_name' => 'פונקציות שורש: תחום וקיצון',
            'generator_focus' => 'תחום של פונקציות שורש, שורש במכנה, נגזרת וקיצון, בדגש על נקודת קצה.'
        ],
        'w08-full-radical-investigation' => [
            'number' => 8,
            'display_name' => 'חקירת שורש מלאה',
            'generator_focus' => 'שאלת חקירת שורש מלאה עם פרמטר, חיתוכים, קיצון, סקיצה ונימוק קצה תחום.'
        ],
        'w09-derivative-graph-selection' => [
            'number' => 9,
            'display_name' => 'מ־f ל־f׳: גרף הנגזרת',
            'generator_focus' => 'מעבר מתכונות f לתחום, נקודות קיצון וסימן של f׳; בחירה בין ארבעה גרפי נגזרת עם מסיחים תקינים.'
        ],
        'w10-area-transformations-and-reciprocal' => [
            'number' => 10,
            'display_name' => 'שטח, טרנספורמציות ו־1/f',
            'generator_focus' => 'שטח באמצעות שינוי ערכי פונקציה, טרנספורמציות, ופונקציה הופכית 1/f תוך שמירת תחום.'
        ],
        'w11-mixed-bagrut-simulation' => [
            'number' => 11,
            'display_name' => 'סימולציית בגרות מעורבת',
            'generator_focus' => 'שאלת בגרות מלאה ומתוזמנת ממשפחה רציונלית או שורש, עם סעיף המשך אחד.'
        ],
        'w12-final-readiness-simulation' => [
            'number' => 12,
            'display_name' => 'סימולציית סיום ומוכנות',
            'generator_focus' => 'שאלת בגרות עצמאית מלאה עם מנגנון שונה מסימולציה קודמת ובקרה עצמית של תחום, סימן וסקיצה.'
        ],
    ];
}

function gradeup_471_find_learning_week(string $weekId): ?array
{
    $weeks = gradeup_471_learning_weeks();
    if (!isset($weeks[$weekId])) {
        return null;
    }

    return ['id' => $weekId] + $weeks[$weekId];
}
