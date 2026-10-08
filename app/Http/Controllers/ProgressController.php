<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuizCompletion;
use App\Services\CompanionService;
use App\Services\LearningExperienceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    public function __construct(
        private CompanionService $companionService,
        private LearningExperienceService $learningExperience
    ) {
    }

    public function complete(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
        ]);

        $user = $request->user();
        $lesson = Lesson::with('quizzes:id,lesson_id')->findOrFail($validated['lesson_id']);
        $quizIds = $lesson->quizzes->pluck('id');

        if ($quizIds->isNotEmpty()) {
            $completedCount = QuizCompletion::query()
                ->where('user_id', $user->id)
                ->whereIn('quiz_id', $quizIds)
                ->distinct('quiz_id')
                ->count('quiz_id');

            if ($completedCount < $quizIds->count()) {
                return response()->json([
                    'message' => 'A leckét csak a hozzá tartozó kvízek sikeres teljesítése után zárhatod le.',
                    'completed_quizzes' => $completedCount,
                    'required_quizzes' => $quizIds->count(),
                ], 409);
            }
        }

        $result = DB::transaction(function () use ($user, $validated): array {
            $progress = LessonProgress::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'lesson_id' => $validated['lesson_id'],
                ],
                ['completed' => true]
            );

            $newCompletion = false;

            if ($progress->wasRecentlyCreated) {
                $this->companionService->awardLearningPoints($user, 10);
                $message = 'Lecke teljesítve! +10 XP';
                $newCompletion = true;
            } elseif (! $progress->completed) {
                $progress->update(['completed' => true]);
                $this->companionService->awardLearningPoints($user, 10);
                $message = 'Lecke teljesítve! +10 XP';
                $newCompletion = true;
            } else {
                $message = 'Ezt a leckét már korábban teljesítetted.';
            }

            $unlocked = $newCompletion
                ? $this->learningExperience->recordLearningActivity($user->fresh())
                : [];

            return [
                'message' => $message,
                'current_xp' => $user->fresh()->xp_points,
                'unlocked_achievements' => $unlocked,
            ];
        });

        return response()->json($result);
    }
}
