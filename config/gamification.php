<?php

$totalLessons = (int) env('GAMIFICATION_TOTAL_LESSONS', 451);
$totalQuizzes = (int) env('GAMIFICATION_TOTAL_QUIZZES', 1353);
$lessonXp = (int) env('GAMIFICATION_LESSON_XP', 10);
$quizXp = (int) env('GAMIFICATION_QUIZ_XP', 5);

return [
    'curriculum' => [
        'lessons' => $totalLessons,
        'quizzes' => $totalQuizzes,
        'lesson_xp' => $lessonXp,
        'quiz_xp' => $quizXp,
        'max_xp' => ($totalLessons * $lessonXp) + ($totalQuizzes * $quizXp),
    ],
    'companion' => [
        'max_level' => 100,
        // Gyorsabb visszajelzés az első szinteken, majd fokozatosan nehezedő görbe.
        'level_curve_exponent' => 1.35,
    ],
];
