<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\LessonProgress;
use App\Models\ProjectSubmission;
use App\Models\QuizCompletion;
use App\Models\User;
use App\Models\UserAchievement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LearningExperienceService
{
    /**
     * Egy valódi, új tanulási teljesítés után frissíti a streaket,
     * majd kiosztja az esetlegesen feloldott achievementeket.
     */
    public function recordLearningActivity(User $user): array
    {
        DB::transaction(function () use ($user): void {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $today = now()->startOfDay();
            $lastActivity = $lockedUser->last_learning_activity_on
                ? Carbon::parse($lockedUser->last_learning_activity_on)->startOfDay()
                : null;

            if (! $lastActivity) {
                $lockedUser->current_streak = 1;
            } elseif ($lastActivity->isSameDay($today)) {
                // Ugyanazon a napon több teljesítés nem növeli újra a streaket.
            } elseif ($lastActivity->copy()->addDay()->isSameDay($today)) {
                $lockedUser->current_streak = max(1, (int) $lockedUser->current_streak + 1);
            } else {
                $lockedUser->current_streak = 1;
            }

            $lockedUser->longest_streak = max(
                (int) $lockedUser->longest_streak,
                (int) $lockedUser->current_streak
            );
            $lockedUser->last_learning_activity_on = $today->toDateString();
            $lockedUser->save();
        });

        return $this->syncAchievements($user->fresh());
    }

    /**
     * Régebbi felhasználóknál is képes utólag kiosztani a már megszerzett jelvényeket.
     */
    public function syncAchievements(User $user): array
    {
        $definitions = $this->ensureDefinitions();
        $metrics = $this->metrics($user);
        $unlocked = [];

        foreach ($definitions as $slug => $definition) {
            if (! $this->qualifies($slug, $metrics)) {
                continue;
            }

            $achievement = $definition['model'];
            $unlock = UserAchievement::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                ],
                ['unlocked_at' => now()]
            );

            if ($unlock->wasRecentlyCreated) {
                $unlocked[] = $this->achievementPayload($achievement, $unlock->unlocked_at);
            }
        }

        return $unlocked;
    }

    public function recentAchievements(User $user, int $limit = 6): array
    {
        $this->ensureDefinitions();

        return UserAchievement::query()
            ->where('user_id', $user->id)
            ->with('achievement')
            ->latest('unlocked_at')
            ->limit($limit)
            ->get()
            ->map(fn (UserAchievement $unlock) => $this->achievementPayload(
                $unlock->achievement,
                $unlock->unlocked_at
            ))
            ->values()
            ->all();
    }

    public function achievementCount(User $user): int
    {
        return UserAchievement::query()
            ->where('user_id', $user->id)
            ->count();
    }

    private function metrics(User $user): array
    {
        return [
            'lessons' => LessonProgress::query()
                ->where('user_id', $user->id)
                ->where('completed', true)
                ->count(),
            'quizzes' => QuizCompletion::query()
                ->where('user_id', $user->id)
                ->count(),
            'projects' => ProjectSubmission::query()
                ->where('user_id', $user->id)
                ->whereNotNull('completed_at')
                ->count(),
            'xp' => (int) $user->xp_points,
            'streak' => (int) $user->current_streak,
        ];
    }

    private function qualifies(string $slug, array $metrics): bool
    {
        return match ($slug) {
            'first-lesson' => $metrics['lessons'] >= 1,
            'lesson-5' => $metrics['lessons'] >= 5,
            'lesson-10' => $metrics['lessons'] >= 10,
            'first-quiz' => $metrics['quizzes'] >= 1,
            'quiz-10' => $metrics['quizzes'] >= 10,
            'first-project' => $metrics['projects'] >= 1,
            'project-5' => $metrics['projects'] >= 5,
            'streak-3' => $metrics['streak'] >= 3,
            'streak-7' => $metrics['streak'] >= 7,
            'xp-100' => $metrics['xp'] >= 100,
            'xp-500' => $metrics['xp'] >= 500,
            'xp-1000' => $metrics['xp'] >= 1000,
            default => false,
        };
    }

    private function ensureDefinitions(): array
    {
        $catalog = [
            'first-lesson' => ['title' => 'Első lépés', 'description' => 'Teljesítetted az első leckédet.', 'icon' => 'bi-flag-fill', 'sort_order' => 10],
            'first-quiz' => ['title' => 'Jó válasz!', 'description' => 'Sikeresen teljesítetted az első kvízedet.', 'icon' => 'bi-patch-check-fill', 'sort_order' => 20],
            'first-project' => ['title' => 'Builder', 'description' => 'Elkészült az első Getingo projekted.', 'icon' => 'bi-code-square', 'sort_order' => 30],
            'streak-3' => ['title' => 'Lendületben', 'description' => '3 egymást követő napon tanultál.', 'icon' => 'bi-fire', 'sort_order' => 40],
            'lesson-5' => ['title' => 'Tanulógép', 'description' => '5 leckét teljesítettél.', 'icon' => 'bi-book-half', 'sort_order' => 50],
            'xp-100' => ['title' => '100 XP klub', 'description' => 'Elérted a 100 XP-t.', 'icon' => 'bi-lightning-charge-fill', 'sort_order' => 60],
            'streak-7' => ['title' => 'Heti sorozat', 'description' => '7 napos tanulási sorozatot értél el.', 'icon' => 'bi-calendar2-check-fill', 'sort_order' => 70],
            'lesson-10' => ['title' => 'Tíz lecke', 'description' => '10 leckét teljesítettél.', 'icon' => 'bi-mortarboard-fill', 'sort_order' => 80],
            'quiz-10' => ['title' => 'Kvízmester', 'description' => '10 kvízt teljesítettél helyesen.', 'icon' => 'bi-trophy-fill', 'sort_order' => 90],
            'project-5' => ['title' => 'Projektépítő', 'description' => '5 projektet fejeztél be.', 'icon' => 'bi-window-stack', 'sort_order' => 100],
            'xp-500' => ['title' => '500 XP', 'description' => 'Elérted az 500 XP-t.', 'icon' => 'bi-stars', 'sort_order' => 110],
            'xp-1000' => ['title' => 'Getingo Veteran', 'description' => 'Elérted az 1000 XP-t.', 'icon' => 'bi-gem', 'sort_order' => 120],
        ];

        Achievement::query()->upsert(
            collect($catalog)
                ->map(fn (array $definition, string $slug) => ['slug' => $slug, ...$definition])
                ->values()
                ->all(),
            ['slug'],
            ['title', 'description', 'icon', 'sort_order']
        );

        $models = Achievement::query()
            ->whereIn('slug', array_keys($catalog))
            ->get()
            ->keyBy('slug');

        $result = [];
        foreach ($catalog as $slug => $definition) {
            $result[$slug] = [
                ...$definition,
                'model' => $models->get($slug),
            ];
        }

        return $result;
    }

    private function achievementPayload(Achievement $achievement, mixed $unlockedAt): array
    {
        return [
            'slug' => $achievement->slug,
            'title' => $achievement->title,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'unlocked_at' => $unlockedAt,
        ];
    }
}
