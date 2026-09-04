<?php

/**
 * קטלוג י״א 4 יח״ל, שנבנה מתוך חוברת תוכנית הלימודים המצורפת.
 */
function gradeup_curriculum_catalog(): array
{
    return [
        'math-11-4' => [
            'subject' => 'מתמטיקה',
            'grade_level' => 11,
            'study_units' => 4,
            'topics' => [
                'statistics-probability' => [
                    'name' => 'סטטיסטיקה',
                    'hours' => 35,
                    'subtopics' => [
                        'mean-standard-deviation' => 'חזרה: ממוצע וסטיית תקן',
                        'standard-score' => 'ציון תקן ומשמעותו',
                        'normal-properties' => 'תכונות עקומת ההתפלגות הנורמלית',
                        'normal-table' => 'הטבלה המצטברת של ההתפלגות הנורמלית',
                        'normal-probability' => 'חישוב הסתברויות, אחוזים וכמויות',
                        'normal-inverse' => 'מציאת ערך, ממוצע או סטיית תקן מנתוני הסתברות',
                        'normal-combined-probability' => 'שילוב התפלגות נורמלית והסתברות',
                        'scatter-plot' => 'קשר סטטיסטי ודיאגרמת פיזור',
                        'averages-graph' => 'גרף ממוצעים וניבוי',
                        'correlation' => 'מקדם המתאם הלינארי',
                        'linear-regression' => 'ישר רגרסיה וניבוי לינארי',
                        'model-limitations' => 'קשר סיבתי, קשר סטטיסטי ומגבלות הניבוי',
                        'statistics-bagrut' => 'שאלה משולבת בסטטיסטיקה בסגנון בגרות',
                    ],
                ],
                'geometry' => [
                    'name' => 'גאומטריה',
                    'hours' => 40,
                    'subtopics' => [
                        'circle-basics-chords' => 'הגדרות במעגל, מיתרים וקשתות',
                        'circle-angles' => 'זוויות מרכזיות והיקפיות',
                        'circle-tangents' => 'משיקים למעגל ותכונותיהם',
                        'cyclic-shapes' => 'משולש ומרובע חסומים במעגל',
                        'inscribed-circle' => 'משולש חוסם מעגל ומרכז המעגל החסום',
                        'circle-measures' => 'היקף מעגל, שטח עיגול וגזרה',
                        'sine-rule' => 'משפט הסינוסים ורדיוס המעגל החוסם',
                        'trig-area' => 'שטחים באמצעות טריגונומטריה',
                        'analytic-circle-equation' => 'משוואה קנונית וכללית של מעגל',
                        'circle-line-position' => 'מצב הדדי בין מעגל לישר',
                        'analytic-tangent' => 'משוואת משיק למעגל',
                        'circle-tangent-axes' => 'מעגל המשיק לציר אחד או לשני הצירים',
                        'integrated-geometry' => 'שילוב גאומטריה אוקלידית, אנליטית וטריגונומטריה',
                        'geometry-bagrut' => 'שאלה משולבת בגאומטריה בסגנון בגרות',
                    ],
                ],
                'calculus' => [
                    'name' => 'פונקציות וחשבון דיפרנציאלי ואינטגרלי',
                    'hours' => 45,
                    'subtopics' => [
                        'rational-pre-analysis' => 'קדם־אנליזה של פונקציה רציונלית',
                        'transformations' => 'טרנספורמציות, שיקופים וערך מוחלט',
                        'derivative-rules' => 'נגזרות של פונקציות רציונליות ופונקציות שורש',
                        'tangent' => 'משוואת משיק לגרף פונקציה',
                        'domain-asymptotes' => 'תחום הגדרה, התנהגות ואסימפטוטות',
                        'intersections-sign' => 'חיתוך עם הצירים, חיוביות ושליליות',
                        'extrema-monotonicity' => 'נקודות קיצון ותחומי עלייה וירידה',
                        'rational-investigation' => 'חקירת פונקציה רציונלית',
                        'radical-investigation' => 'חקירת פונקציית שורש',
                        'even-odd' => 'זוגיות ואי־זוגיות של פונקציות',
                        'function-derivative-graph' => 'הקשר בין גרף הפונקציה לגרף הנגזרת',
                        'parameters' => 'חקירה ושאלות עם פרמטרים',
                        'optimization' => 'שאלות ערך קיצון בתחום פתוח וסגור',
                        'optimization-applications' => 'בעיות קיצון מספריות, כלכליות וגאומטריות',
                        'antiderivative' => 'פונקציה קדומה ואינטגרל לא מסוים',
                        'definite-integral' => 'אינטגרל מסוים',
                        'area-axis' => 'שטח בין גרף פונקציה לציר x',
                        'area-between-graphs' => 'שטח בין שני גרפים ושטחים מורכבים',
                        'integral-parameter' => 'שימוש בפרמטר בשאלות אינטגרל',
                        'calculus-bagrut' => 'חקירת פונקציה מלאה בסגנון בגרות',
                    ],
                ],
            ],
        ],
    ];
}

function gradeup_curriculum_for_group(string $subjectName, int $gradeLevel, ?int $studyUnits): ?array
{
    $normalizedSubject = strtolower(trim($subjectName));
    $isMath = in_array($normalizedSubject, ['math', 'mathematics', 'מתמטיקה'], true);
    if (!$isMath) {
        return null;
    }
    return gradeup_curriculum_catalog()['math-' . $gradeLevel . '-' . (int)$studyUnits] ?? null;
}

function gradeup_curriculum_selection(array $catalog, string $topicId, string $subtopicId): ?array
{
    if (!isset($catalog['topics'][$topicId]['subtopics'][$subtopicId])) {
        return null;
    }
    return [
        'topic_id' => $topicId,
        'topic_name' => $catalog['topics'][$topicId]['name'],
        'subtopic_id' => $subtopicId,
        'subtopic_name' => $catalog['topics'][$topicId]['subtopics'][$subtopicId],
        'display_name' => $catalog['topics'][$topicId]['name'] . ' — ' . $catalog['topics'][$topicId]['subtopics'][$subtopicId],
    ];
}

