<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\LessonProgress;
use App\Models\Note;
use App\Models\ProjectSubmission;
use App\Models\QuizCompletion;
use App\Services\CompanionService;
use App\Services\LearningExperienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function __construct(
        private LearningExperienceService $learningExperience,
        private CompanionService $companionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Régebbi felhasználóknál is utólag feloldja azokat a badge-eket,
        // amelyekhez a feltételek már korábban teljesültek.
        $this->learningExperience->syncAchievements($user);
        $user->refresh();

        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->pluck('lesson_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $completedLookup = array_fill_keys($completedLessonIds, true);

        $categories = Category::query()
            ->with(['lessons' => fn ($query) => $query->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $learningPath = [];
        $nextLesson = null;
        $totalLessons = 0;
        $completedLessons = 0;

        foreach ($categories as $category) {
            $lessonCount = $category->lessons->count();
            $completedInCategory = $category->lessons
                ->filter(fn ($lesson) => isset($completedLookup[$lesson->id]))
                ->count();

            $totalLessons += $lessonCount;
            $completedLessons += $completedInCategory;

            $nextInCategory = $category->lessons
                ->first(fn ($lesson) => ! isset($completedLookup[$lesson->id]));

            if (! $nextLesson && $nextInCategory) {
                $nextLesson = [
                    'id' => $nextInCategory->id,
                    'title' => $nextInCategory->title,
                    'slug' => $nextInCategory->slug,
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'category_slug' => $category->slug,
                    'url' => '/categories/'.$category->id.'/lessons?lesson='.$nextInCategory->id,
                ];
            }

            $learningPath[] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'completed_lessons' => $completedInCategory,
                'total_lessons' => $lessonCount,
                'progress_percentage' => $lessonCount > 0
                    ? (int) round(($completedInCategory / $lessonCount) * 100)
                    : 0,
                'is_completed' => $lessonCount > 0 && $completedInCategory >= $lessonCount,
                'next_lesson_id' => $nextInCategory?->id,
            ];
        }

        $progressPercentage = $totalLessons > 0
            ? (int) round(($completedLessons / $totalLessons) * 100)
            : 0;

        $today = now()->toDateString();
        $lessonToday = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->whereDate('updated_at', $today)
            ->exists();
        $quizToday = QuizCompletion::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->exists();
        $projectToday = ProjectSubmission::query()
            ->where('user_id', $user->id)
            ->whereDate('updated_at', $today)
            ->exists();

        $dailyGoals = [
            [
                'key' => 'lesson',
                'title' => 'Teljesíts 1 leckét',
                'description' => 'Haladj egy lépéssel tovább a tanulási útvonaladon.',
                'completed' => $lessonToday,
                'url' => $nextLesson['url'] ?? '/categories',
            ],
            [
                'key' => 'quiz',
                'title' => 'Oldj meg 1 kvízt',
                'description' => 'Ellenőrizd, hogy megmaradt-e, amit megtanultál.',
                'completed' => $quizToday,
                'url' => $nextLesson['url'] ?? '/categories',
            ],
            [
                'key' => 'project',
                'title' => 'Dolgozz 1 projekten',
                'description' => 'A tudás akkor rögzül igazán, amikor építesz vele valamit.',
                'completed' => $projectToday,
                'url' => '/projects',
            ],
        ];

        $completedGoals = collect($dailyGoals)->where('completed', true)->count();
        $completedProjects = ProjectSubmission::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->count();
        $completedQuizzes = QuizCompletion::query()
            ->where('user_id', $user->id)
            ->count();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'xp_points' => (int) $user->xp_points,
                'current_streak' => (int) $user->current_streak,
                'longest_streak' => (int) $user->longest_streak,
                'last_learning_activity_on' => $user->last_learning_activity_on,
            ],
            'stats' => [
                'progress_percentage' => $progressPercentage,
                'completed_lessons_count' => $completedLessons,
                'total_lessons_count' => $totalLessons,
                'completed_quizzes_count' => $completedQuizzes,
                'completed_projects_count' => $completedProjects,
                'achievements_count' => $this->learningExperience->achievementCount($user),
            ],
            'next_lesson' => $nextLesson,
            'learning_path' => $learningPath,
            'daily_goals' => $dailyGoals,
            'daily_progress_percentage' => (int) round(($completedGoals / count($dailyGoals)) * 100),
            'recent_achievements' => $this->learningExperience->recentAchievements($user, 6),
            'companion' => $this->companionService->state($user),
            'notes' => Note::where('user_id', $user->id)
                ->with('lesson:id,title,category_id')
                ->latest('updated_at')
                ->limit(20)
                ->get(),
        ]);
    }
}
