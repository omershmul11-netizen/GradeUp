<?php

require_once __DIR__ . '/../bagrut471/curriculum_catalog.php';
require_once __DIR__ . '/../bagrut471/learning_weeks.php';

$catalog = gradeup_curriculum_catalog()['math-11-4'];
$calculus = $catalog['topics']['calculus'];
$expectedHours = $calculus['subtopic_hours'];

if (array_sum($expectedHours) !== 45 || (int)$calculus['hours'] !== 45) {
    throw new RuntimeException('The calculus curriculum must total 45 hours.');
}

$allocatedHours = [];
$weekTotal = 0;
foreach (gradeup_471_learning_weeks() as $week) {
    $weekTotal += (int)$week['hours'];
    if (array_sum($week['hour_allocation']) !== (int)$week['hours']) {
        throw new RuntimeException('A weekly hour allocation does not match its total.');
    }
    foreach ($week['hour_allocation'] as $subtopicId => $hours) {
        $allocatedHours[$subtopicId] = ($allocatedHours[$subtopicId] ?? 0) + (int)$hours;
    }
}

ksort($allocatedHours);
ksort($expectedHours);
if ($weekTotal !== 45 || $allocatedHours !== $expectedHours) {
    throw new RuntimeException('The 12-week plan does not match the subtopic allocation.');
}

foreach ($calculus['subtopics'] as $subtopicId => $name) {
    $selection = gradeup_curriculum_selection($catalog, 'calculus', $subtopicId);
    $expectedMode = $subtopicId === 'calculus-bagrut' ? 'summary_question' : 'practice_worksheet';
    if (($selection['generation_mode'] ?? '') !== $expectedMode) {
        throw new RuntimeException('Unexpected generation mode for ' . $subtopicId . '.');
    }
}

echo "471 curriculum validation passed.\n";
