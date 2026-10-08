<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Note;
use App\Models\Project;
use App\Models\Quiz;
use App\Models\QuizCompletion;
use App\Models\User;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function stats()
    {
        $totalUsers = User::count();
        $students = User::where('role', 'student')->count();
        $admins = User::where('role', 'admin')->count();
        $bannedUsers = User::where('is_banned', true)->count();

        $totalLessons = Lesson::count();
        $totalProjects = Project::count();
        $totalQuizzes = Quiz::count();
        $totalCategories = Category::count();

        $totalNotes = Note::count();
        $totalFavorites = Favorite::count();
        $completedLessons = LessonProgress::count();
        $completedQuizzes = QuizCompletion::count();

        $usersToday = User::whereDate('created_at', Carbon::today())->count();
        $usersThisWeek = User::whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->count();

        $usersThisMonth = User::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $recentUsers = User::select('id', 'name', 'email', 'role', 'created_at')
            ->latest()
            ->take(5)
            ->get();

        $topCategories = Category::withCount('lessons')
            ->orderByDesc('lessons_count')
            ->take(6)
            ->get(['id', 'name']);

        $completionRate = $totalLessons > 0 && $totalUsers > 0
            ? round(($completedLessons / ($totalLessons * max($students, 1))) * 100, 1)
            : 0;

        $quizCompletionRate = $totalQuizzes > 0 && $totalUsers > 0
            ? round(($completedQuizzes / ($totalQuizzes * max($students, 1))) * 100, 1)
            : 0;

        $engagementScore = round(min(100, (
            ($totalNotes * 1.2) +
            ($totalFavorites * 0.8) +
            ($completedLessons * 1.5) +
            ($completedQuizzes * 1.5)
        ) / max($students, 1)), 1);

        return response()->json([
            'summary' => [
                'total_users' => $totalUsers,
                'students' => $students,
                'admins' => $admins,
                'banned_users' => $bannedUsers,
                'total_lessons' => $totalLessons,
                'total_projects' => $totalProjects,
                'total_quizzes' => $totalQuizzes,
                'total_categories' => $totalCategories,
                'total_notes' => $totalNotes,
                'total_favorites' => $totalFavorites,
                'completed_lessons' => $completedLessons,
                'completed_quizzes' => $completedQuizzes
            ],
            'growth' => [
                'users_today' => $usersToday,
                'users_this_week' => $usersThisWeek,
                'users_this_month' => $usersThisMonth
            ],
            'performance' => [
                'lesson_completion_rate' => $completionRate,
                'quiz_completion_rate' => $quizCompletionRate,
                'engagement_score' => $engagementScore
            ],
            'top_categories' => $topCategories,
            'recent_users' => $recentUsers
        ]);
    }
        public function index()
    {
        $totalUsers = User::count();
        $students = User::where('role', 'student')->count();
        $admins = User::where('role', 'admin')->count();
        $bannedUsers = User::where('is_banned', true)->count();

        $totalLessons = Lesson::count();
        $totalProjects = Project::count();
        $totalQuizzes = Quiz::count();
        $totalCategories = Category::count();

        $totalNotes = Note::count();
        $totalFavorites = Favorite::count();
        $completedLessons = LessonProgress::count();
        $completedQuizzes = QuizCompletion::count();

        $usersToday = User::whereDate(
            'created_at',
            Carbon::today()
        )->count();

        $usersThisWeek = User::whereBetween(
            'created_at',
            [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ]
        )->count();

        $usersThisMonth = User::whereMonth(
            'created_at',
            Carbon::now()->month
        )
            ->whereYear(
                'created_at',
                Carbon::now()->year
            )
            ->count();

        $recentUsers = User::select(
            'id',
            'name',
            'email',
            'role',
            'created_at'
        )
            ->latest()
            ->take(5)
            ->get();

        $topCategories = Category::withCount('lessons')
            ->orderByDesc('lessons_count')
            ->take(6)
            ->get([
                'id',
                'name'
            ]);

        $lessonCompletionRate =
            $totalLessons > 0 && $students > 0
                ? round(
                    (
                        $completedLessons /
                        ($totalLessons * $students)
                    ) * 100,
                    1
                )
                : 0;

        $quizCompletionRate =
            $totalQuizzes > 0 && $students > 0
                ? round(
                    (
                        $completedQuizzes /
                        ($totalQuizzes * $students)
                    ) * 100,
                    1
                )
                : 0;

        $engagementScore = round(
            min(
                100,
                (
                    ($totalNotes * 1.2) +
                    ($totalFavorites * 0.8) +
                    ($completedLessons * 1.5) +
                    ($completedQuizzes * 1.5)
                ) / max($students, 1)
            ),
            1
        );

        return response()->json([
            'summary' => [
                'total_users' => $totalUsers,
                'students' => $students,
                'admins' => $admins,
                'banned_users' => $bannedUsers,
                'total_lessons' => $totalLessons,
                'total_projects' => $totalProjects,
                'total_quizzes' => $totalQuizzes,
                'total_categories' => $totalCategories,
                'total_notes' => $totalNotes,
                'total_favorites' => $totalFavorites,
                'completed_lessons' => $completedLessons,
                'completed_quizzes' => $completedQuizzes
            ],
            'growth' => [
                'users_today' => $usersToday,
                'users_this_week' => $usersThisWeek,
                'users_this_month' => $usersThisMonth
            ],
            'performance' => [
                'lesson_completion_rate' =>
                    $lessonCompletionRate,
                'quiz_completion_rate' =>
                    $quizCompletionRate,
                'engagement_score' =>
                    $engagementScore
            ],
            'top_categories' => $topCategories,
            'recent_users' => $recentUsers
        ]);
    }

}