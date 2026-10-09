<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminExerciseController;
use App\Http\Controllers\Admin\AdminLessonController;
use App\Http\Controllers\Admin\AdminLessonSectionController;
use App\Http\Controllers\Admin\AdminProjectController;
use App\Http\Controllers\Admin\AdminQuizController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminDeletedUserController;
use App\Http\Controllers\Admin\AdminSensitiveController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanionController;
use App\Http\Controllers\CodeExecutionController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LessonCodeNoteController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Student\LessonController;
use App\Http\Controllers\Student\ExerciseController as StudentExerciseController;
use App\Http\Controllers\Student\ProjectController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BugReportController;
use App\Http\Controllers\Admin\AdminBugReportController;


Route::middleware('throttle:public-api')->group(function () {
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/kategoriak', [CategoryController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category_id}/lessons', [LessonController::class, 'index'])
        ->whereNumber('category_id');
    Route::get('/categories/{category_id}/curriculum', [LessonController::class, 'curriculum'])
        ->whereNumber('category_id');
    Route::get('/lessons/{lessonId}/quizzes', [QuizController::class, 'byLesson'])
        ->whereNumber('lessonId');
    Route::post('/quizzes/{quiz}/check', [QuizController::class, 'check'])
        ->whereNumber('quiz');
});

Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:search');

Route::get('/billing/plans', [BillingController::class, 'plans'])
    ->middleware('throttle:public-api');

Route::get('/health', HealthController::class)->middleware('throttle:public-api');

Route::get('/code/capabilities', [CodeExecutionController::class, 'capabilities'])
    ->middleware('throttle:public-api');


Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

Route::post('/regisztracio', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::post('/bejelentkezes', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:password-reset');

Route::post('/password/reset', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:password-reset');

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth:sanctum', 'active', 'throttle:user-api', 'no-store'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/bug-reports', [BugReportController::class, 'store']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/billing/status', [BillingController::class, 'status']);

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');

    Route::patch('/account', [AccountController::class, 'update']);

    Route::put('/account/password', [AccountController::class, 'changePassword'])
        ->middleware('throttle:gdpr');

    // GDPR - email megerősítés nélkül is elérhető legyen
    Route::get('/gdpr/export', [GdprController::class, 'exportData'])
        ->middleware('throttle:gdpr');

    Route::delete('/gdpr/delete-account', [GdprController::class, 'deleteAccount'])
        ->middleware('throttle:gdpr');

    Route::middleware('verified')->group(function () {
        Route::post('/billing/checkout', [BillingController::class, 'checkout'])
            ->middleware('throttle:6,1');
        Route::post('/billing/portal', [BillingController::class, 'portal'])
            ->middleware('throttle:6,1');

        Route::get('/dashboard', [StudentDashboardController::class, 'index']);

        Route::post('/code/run', [CodeExecutionController::class, 'run'])
            ->middleware('throttle:code-runner');

        Route::get('/exercises', [StudentExerciseController::class, 'index']);
        Route::get('/exercises/{exercise}', [StudentExerciseController::class, 'show'])->whereNumber('exercise');
        Route::put('/exercises/{exercise}/workspace', [StudentExerciseController::class, 'saveWorkspace'])->whereNumber('exercise');

        Route::get('/projects', [ProjectController::class, 'index']);
        Route::get('/portfolio', [ProjectController::class, 'portfolio']);
        Route::get('/projects/{project}', [ProjectController::class, 'show'])
            ->whereNumber('project');
        Route::post('/projects/{project}/start', [ProjectController::class, 'start'])->whereNumber('project');
        Route::post('/projects/{project}/restart', [ProjectController::class, 'restart'])->whereNumber('project');
        Route::put('/projects/{project}/workspace', [ProjectController::class, 'saveWorkspace'])
            ->whereNumber('project');
        Route::post('/projects/{project}/check', [ProjectController::class, 'check'])
            ->whereNumber('project')
            ->middleware('throttle:quiz');
        Route::post('/projects/{project}/mentor', [ProjectController::class, 'mentor'])
            ->whereNumber('project')
            ->middleware('throttle:quiz');

        Route::get('/companion', [CompanionController::class, 'show']);
        Route::post('/companion/action', [CompanionController::class, 'action']);
        Route::patch('/companion/preferences', [CompanionController::class, 'preferences']);

        Route::apiResource('notes', NoteController::class);

        Route::post('/progress', [ProgressController::class, 'complete']);

        Route::post('/favorites/toggle', [FavoriteController::class, 'toggle']);

        Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submit'])
            ->middleware('throttle:quiz');

        Route::get('/lessons/{lesson}/personal-code', [LessonCodeNoteController::class, 'show']);
        Route::put('/lessons/{lesson}/personal-code', [LessonCodeNoteController::class, 'update']);
        Route::delete('/lessons/{lesson}/personal-code', [LessonCodeNoteController::class, 'destroy']);
    });
});

Route::middleware([
    'auth:sanctum',
    'active',
    'admin',
    'throttle:admin-api',
    'no-store',
])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/dashboard-stats', [AdminDashboardController::class, 'stats']);
    Route::post('/sensitive/unlock', [AdminSensitiveController::class, 'unlock'])->middleware('throttle:6,1');
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->middleware('admin.reauth');
    Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->middleware('admin.reauth');
    Route::get('/subscriptions', [AdminBillingController::class, 'subscriptions']);
    Route::get('/revenue', [AdminBillingController::class, 'revenue']);
    Route::get('/revenue/export', [AdminBillingController::class, 'exportRevenue']);
    Route::post('/revenue/sync', [AdminBillingController::class, 'syncStripe'])->middleware('throttle:6,1');

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::patch('/users/{id}/role', [AdminUserController::class, 'updateRole'])->whereNumber('id');
    Route::post('/users/{id}/toggle-ban', [AdminUserController::class, 'toggleBan'])->whereNumber('id');
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->whereNumber('id');
    Route::post('/users/{id}/delete', [AdminUserController::class, 'destroy'])->whereNumber('id');
    Route::get('/deleted-users', [AdminDeletedUserController::class, 'index']);
    Route::get('/deleted-users/{deletedUser}/evidence', [AdminDeletedUserController::class, 'evidence'])->middleware('admin.reauth')->whereNumber('deletedUser');

    Route::apiResource('lesson-sections', AdminLessonSectionController::class);
    Route::apiResource('lessons', AdminLessonController::class);
    Route::apiResource('exercises', AdminExerciseController::class);
    Route::apiResource('projects', AdminProjectController::class);
    Route::apiResource('quizzes', AdminQuizController::class);
    Route::apiResource('categories', AdminCategoryController::class);

    Route::get('/bug-reports/unread-count', [AdminBugReportController::class, 'unreadCount']);
    Route::post('/bug-reports/mark-all-seen', [AdminBugReportController::class, 'markAllSeen']);
    Route::get('/bug-reports', [AdminBugReportController::class, 'index']);
    Route::get('/bug-reports/{bugReport}', [AdminBugReportController::class, 'show'])
        ->whereNumber('bugReport');
    Route::patch('/bug-reports/{bugReport}', [AdminBugReportController::class, 'update'])
        ->whereNumber('bugReport');
    Route::get('/bug-reports/{bugReport}/screenshot', [AdminBugReportController::class, 'screenshot'])
        ->whereNumber('bugReport');
});
