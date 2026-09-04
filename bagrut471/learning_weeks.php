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
            'display_name' => 'מבנה פונקציה ותחום הגדרה',
            'hours' => 4,
            'subtopics' => ['rational-pre-analysis', 'domain-asymptotes'],
            'hour_allocation' => ['rational-pre-analysis' => 2, 'domain-asymptotes' => 2],
            'generator_focus' => 'קדם־אנליזה רציונלית ותחום הגדרה. הפק דפי עבודה מדורגים בלבד; אין לנסח שאלת חקירה מלאה.'
        ],
        'w02-transformations-and-tangent' => [
            'number' => 2,
            'display_name' => 'אסימפטוטות, חיתוכים וסימן',
            'hours' => 3,
            'subtopics' => ['domain-asymptotes', 'intersections-sign'],
            'hour_allocation' => ['domain-asymptotes' => 1, 'intersections-sign' => 2],
            'generator_focus' => 'השלמת תחום ואסימפטוטות, חיתוך עם הצירים וחיוביות/שליליות בדפי עבודה קצרים ומדורגים.'
        ],
        'w03-product-and-quotient-rules' => [
            'number' => 3,
            'display_name' => 'נגזרות ומשוואת משיק',
            'hours' => 4,
            'subtopics' => ['derivative-rules', 'tangent'],
            'hour_allocation' => ['derivative-rules' => 3, 'tangent' => 1],
            'generator_focus' => 'נגזרות רציונליות ושורש ומשוואת משיק. כל תרגיל מתרגל מיומנות מוגדרת; אין חקירה מלאה.'
        ],
        'w04-derivative-sign-and-extrema' => [
            'number' => 4,
            'display_name' => 'נקודות קיצון ועלייה וירידה',
            'hours' => 4,
            'subtopics' => ['tangent', 'extrema-monotonicity'],
            'hour_allocation' => ['tangent' => 1, 'extrema-monotonicity' => 3],
            'checkpoint' => true,
            'generator_focus' => 'טבלת סימני נגזרת, נקודות קיצון ותחומי עלייה/ירידה, כולל קצה תחום. סיים בבדיקת שליטה קצרה.'
        ],
        'w05-rational-asymptotes' => [
            'number' => 5,
            'display_name' => 'חקירה רציונלית וטרנספורמציות',
            'hours' => 4,
            'subtopics' => ['rational-investigation', 'transformations'],
            'hour_allocation' => ['rational-investigation' => 3, 'transformations' => 1],
            'generator_focus' => 'דף עבודה מדורג ברכיבי חקירה רציונלית ולאחריו העברת תכונות בטרנספורמציה. אין שאלה מלאה.'
        ],
        'w06-full-rational-investigation' => [
            'number' => 6,
            'display_name' => 'חקירת שורש וטרנספורמציות',
            'hours' => 4,
            'subtopics' => ['radical-investigation', 'transformations'],
            'hour_allocation' => ['radical-investigation' => 3, 'transformations' => 1],
            'generator_focus' => 'דף עבודה מדורג לחקירת פונקציית שורש, נקודת קצה והעברת תכונות. אין שאלה מלאה.'
        ],
        'w07-radical-domain-and-extrema' => [
            'number' => 7,
            'display_name' => 'פרמטרים והקשר בין פונקציה לנגזרת',
            'hours' => 4,
            'subtopics' => ['parameters', 'function-derivative-graph'],
            'hour_allocation' => ['parameters' => 3, 'function-derivative-graph' => 1],
            'generator_focus' => 'תרגול פרמטר מנתון והסקת תכונות בין גרף הפונקציה לגרף הנגזרת. דף עבודה, לא שאלה מלאה.'
        ],
        'w08-full-radical-investigation' => [
            'number' => 8,
            'display_name' => 'גרף נגזרת, זוגיות וקיצון בתחום',
            'hours' => 4,
            'subtopics' => ['function-derivative-graph', 'even-odd', 'optimization'],
            'hour_allocation' => ['function-derivative-graph' => 1, 'even-odd' => 1, 'optimization' => 2],
            'checkpoint' => true,
            'generator_focus' => 'דפי עבודה על קשר פונקציה־נגזרת, זוגיות ואי־זוגיות ושאלות ערך קיצון בתחום פתוח וסגור.'
        ],
        'w09-derivative-graph-selection' => [
            'number' => 9,
            'display_name' => 'בעיות קיצון יישומיות',
            'hours' => 3,
            'subtopics' => ['optimization-applications'],
            'hour_allocation' => ['optimization-applications' => 3],
            'generator_focus' => 'דף עבודה מדורג בבעיות קיצון מספריות, כלכליות וגאומטריות: בניית מודל, תחום, נגזרת ובדיקת תשובה.'
        ],
        'w10-area-transformations-and-reciprocal' => [
            'number' => 10,
            'display_name' => 'פונקציה קדומה ואינטגרל מסוים',
            'hours' => 4,
            'subtopics' => ['antiderivative', 'definite-integral'],
            'hour_allocation' => ['antiderivative' => 2, 'definite-integral' => 2],
            'generator_focus' => 'דפי עבודה מדורגים על פונקציה קדומה, אינטגרל לא מסוים ומשמעות האינטגרל המסוים.'
        ],
        'w11-mixed-bagrut-simulation' => [
            'number' => 11,
            'display_name' => 'שטחים באמצעות אינטגרל',
            'hours' => 4,
            'subtopics' => ['area-axis', 'area-between-graphs'],
            'hour_allocation' => ['area-axis' => 2, 'area-between-graphs' => 2],
            'generator_focus' => 'דפי עבודה על שטח בין גרף לציר x, בין שני גרפים ושטחים מורכבים. אין שאלת בגרות מלאה.'
        ],
        'w12-final-readiness-simulation' => [
            'number' => 12,
            'display_name' => 'פרמטר באינטגרל ושאלת סיכום',
            'hours' => 3,
            'subtopics' => ['integral-parameter', 'calculus-bagrut'],
            'hour_allocation' => ['integral-parameter' => 1, 'calculus-bagrut' => 2],
            'checkpoint' => true,
            'generator_focus' => 'תחילה דף עבודה קצר על פרמטר באינטגרל; רק בבחירת calculus-bagrut הפק שאלת חקירה מלאה בסגנון בגרות.'
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
