<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizCompletion;
use App\Services\CompanionService;
use App\Services\LearningExperienceService;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(
        private CompanionService $companionService,
        private LearningExperienceService $learningExperience
    ) {
    }

    public function byLesson($lessonId)
    {
        $quizzes = Quiz::where('lesson_id', $lessonId)
            ->whereHas('lesson', fn ($query) => $query->where('is_published', true))
            ->select(
                'id',
                'lesson_id',
                'question',
                'option_a',
                'option_b',
                'option_c',
                'option_d'
            )
            ->get();

        return response()->json($quizzes);
    }

    public function check(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->lesson()->where('is_published', true)->exists(), 404);
        $validated = $request->validate([
            'answer' => 'required|in:a,b,c,d'
        ]);

        $correct = $validated['answer'] === $quiz->correct_answer;

        return response()->json([
            'correct' => $correct,
            'message' => $correct
                ? 'Helyes válasz!'
                : 'Helytelen válasz, próbáld újra!'
        ]);
    }

    public function submit(Request $request, Quiz $quiz)
    {
        abort_unless($quiz->lesson()->where('is_published', true)->exists(), 404);
        $validated = $request->validate([
            'answer' => 'required|in:a,b,c,d'
        ]);

        if ($validated['answer'] !== $quiz->correct_answer) {
            return response()->json([
                'correct' => false,
                'message' => 'Helytelen válasz, próbáld újra!'
            ]);
        }

        $user = $request->user();

        $completion = QuizCompletion::firstOrCreate([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id
        ]);

        if ($completion->wasRecentlyCreated) {
            $this->companionService->awardLearningPoints($user, 5);
            $unlocked = $this->learningExperience->recordLearningActivity($user->fresh());

            return response()->json([
                'correct' => true,
                'message' => 'Helyes válasz! +5 XP',
                'xp_awarded' => 5,
                'current_xp' => $user->fresh()->xp_points,
                'unlocked_achievements' => $unlocked,
            ]);
        }

        return response()->json([
            'correct' => true,
            'message' => 'Helyes válasz! Ezt a kvízt már korábban teljesítetted.',
            'xp_awarded' => 0,
            'current_xp' => $user->xp_points,
            'unlocked_achievements' => [],
        ]);
    }
}
