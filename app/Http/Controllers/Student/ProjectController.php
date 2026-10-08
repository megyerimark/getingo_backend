<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectSubmission;
use App\Services\CompanionService;
use App\Services\LearningExperienceService;
use App\Services\ProjectMentorService;
use App\Services\ProjectValidationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $projects = Project::query()
            ->select('id', 'title', 'description', 'difficulty', 'estimated_time', 'xp_reward', 'created_at', 'updated_at')
            ->with(['submissions' => function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->select('id', 'user_id', 'project_id', 'completed_at', 'started_at', 'expires_at');
            }])
            ->latest('id')
            ->get()
            ->map(function (Project $project): array {
                $submission = $project->submissions->first();

                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'difficulty' => $project->difficulty,
                    'estimated_time' => $project->estimated_time,
                    'xp_reward' => $project->xp_reward,
                    'is_completed' => (bool) $submission?->completed_at,
                    ...$this->timingPayload($submission),
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ];
            });

        return response()->json(['projects' => $projects]);
    }

    public function show(Request $request, Project $project, ProjectValidationService $validator): JsonResponse
    {
        $submission = $this->submissionForUser($request, $project);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'difficulty' => $project->difficulty,
                'estimated_time' => $project->estimated_time,
                'starter_html' => $project->starter_html ?? '',
                'starter_css' => $project->starter_css ?? '',
                'starter_javascript' => $project->starter_javascript ?? '',
                'validation_type' => $project->validation_type,
                'validation_configured' => filled($project->expected_output),
                'validation_trusted' => $validator->isTrustedType($project->validation_type),
                'xp_reward' => $project->xp_reward,
                'is_completed' => (bool) $submission->completed_at,
                ...$this->timingPayload($submission),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ],
            'submission' => [
                'html_code' => $submission->html_code ?? '',
                'css_code' => $submission->css_code ?? '',
                'javascript_code' => $submission->javascript_code ?? '',
                'started_at' => $submission->started_at,
                'expires_at' => $submission->expires_at,
                'completed_at' => $submission->completed_at,
                'xp_awarded' => $submission->xp_awarded,
            ],
        ]);
    }

    public function start(Request $request, Project $project): JsonResponse
    {
        $submission = $this->submissionForUser($request, $project);

        if ($submission->completed_at) {
            return response()->json([
                'message' => 'Ezt a projektet már teljesítetted.',
                ...$this->timingPayload($submission),
            ], 409);
        }

        if ($submission->started_at && ! $submission->expires_at?->isPast()) {
            return response()->json([
                'message' => 'A projekt már fut.',
                ...$this->timingPayload($submission),
            ]);
        }

        if ($submission->started_at && $submission->expires_at?->isPast()) {
            return response()->json([
                'message' => 'A korábbi időkeret lejárt. Használd az Újraindítás gombot.',
                ...$this->timingPayload($submission),
            ], 423);
        }

        $submission->forceFill([
            'started_at' => now(),
            'expires_at' => now()->addMinutes(max(1, (int) $project->estimated_time)),
        ])->save();

        return response()->json([
            'message' => 'A projekt elindult. Sok sikert!',
            ...$this->timingPayload($submission->fresh()),
        ]);
    }

    public function restart(Request $request, Project $project): JsonResponse
    {
        $submission = $this->submissionForUser($request, $project);

        if ($submission->completed_at) {
            return response()->json(['message' => 'A teljesített projektet nem kell újraindítani.'], 409);
        }

        if (! $submission->started_at || ! $submission->expires_at?->isPast()) {
            return response()->json(['message' => 'A projekt csak lejárt időkeret után indítható újra.'], 409);
        }

        $submission->forceFill([
            'started_at' => now(),
            'expires_at' => now()->addMinutes(max(1, (int) $project->estimated_time)),
            'last_console_output' => null,
        ])->save();

        return response()->json([
            'message' => 'Új időkeretet kaptál. A projekt újraindult.',
            ...$this->timingPayload($submission->fresh()),
        ]);
    }

    public function portfolio(Request $request): JsonResponse
    {
        $items = ProjectSubmission::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('completed_at')
            ->with(['project:id,title,description,difficulty,estimated_time,xp_reward'])
            ->latest('completed_at')
            ->get()
            ->map(function (ProjectSubmission $submission): array {
                return [
                    'id' => $submission->id,
                    'project_id' => $submission->project_id,
                    'title' => $submission->project?->title ?? 'Projekt',
                    'description' => $submission->project?->description ?? '',
                    'difficulty' => $submission->project?->difficulty ?? '',
                    'estimated_time' => (int) ($submission->project?->estimated_time ?? 0),
                    'xp_awarded' => (int) $submission->xp_awarded,
                    'completed_at' => $submission->completed_at,
                    'html_code' => $submission->html_code ?? '',
                    'css_code' => $submission->css_code ?? '',
                    'javascript_code' => $submission->javascript_code ?? '',
                ];
            })
            ->values();

        return response()->json(['projects' => $items, 'count' => $items->count()]);
    }

    public function saveWorkspace(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'html_code' => ['nullable', 'string', 'max:200000'],
            'css_code' => ['nullable', 'string', 'max:200000'],
            'javascript_code' => ['nullable', 'string', 'max:200000'],
        ]);

        $submission = $this->submissionForUser($request, $project);
        $this->assertStarted($submission);
        $this->assertNotExpired($submission);

        $submission->fill([
            'html_code' => $validated['html_code'] ?? '',
            'css_code' => $validated['css_code'] ?? '',
            'javascript_code' => $validated['javascript_code'] ?? '',
        ])->save();

        return response()->json([
            'message' => 'A projektmunkád elmentve.',
            'completed_at' => $submission->completed_at,
            ...$this->timingPayload($submission),
        ]);
    }

    public function mentor(Request $request, Project $project, ProjectMentorService $mentor): JsonResponse
    {
        $validated = $request->validate([
            'console_output' => ['sometimes', 'array', 'max:200'],
            'console_output.*' => ['string', 'max:2000'],
            'html_code' => ['nullable', 'string', 'max:200000'],
            'css_code' => ['nullable', 'string', 'max:200000'],
            'javascript_code' => ['nullable', 'string', 'max:200000'],
        ]);

        $submission = $this->submissionForUser($request, $project);
        $this->assertStarted($submission);
        $this->assertNotExpired($submission);

        return response()->json(
            $mentor->analyze($project, $validated, (bool) $request->user()->is_premium)
        );
    }

    public function check(
        Request $request,
        Project $project,
        CompanionService $companionService,
        LearningExperienceService $learningExperience,
        ProjectValidationService $validator
    ): JsonResponse {
        $validated = $request->validate([
            'console_output' => ['sometimes', 'array', 'max:200'],
            'console_output.*' => ['string', 'max:2000'],
            'html_code' => ['nullable', 'string', 'max:200000'],
            'css_code' => ['nullable', 'string', 'max:200000'],
            'javascript_code' => ['nullable', 'string', 'max:200000'],
        ]);

        $submission = $this->submissionForUser($request, $project);
        $this->assertStarted($submission);
        $this->assertNotExpired($submission);

        if (! filled($project->expected_output)) {
            throw ValidationException::withMessages([
                'project' => 'Ehhez a projekthez az admin még nem állított be automatikus ellenőrzést.',
            ]);
        }

        $result = $validator->validate($project, $validated);
        $actual = $result['console_output'];

        $submission->fill([
            'html_code' => $validated['html_code'] ?? '',
            'css_code' => $validated['css_code'] ?? '',
            'javascript_code' => $validated['javascript_code'] ?? '',
            'last_console_output' => implode("\n", $actual),
        ])->save();

        if (! $result['passed']) {
            return response()->json([
                'passed' => false,
                'verified' => (bool) $result['trusted'],
                'message' => 'Még nem teljesen jó. A Getingo Mentor segíthet megtalálni, hol csúszott el a megoldás.',
                'console_output' => $actual,
                'is_completed' => (bool) $submission->completed_at,
                ...$this->timingPayload($submission),
            ]);
        }

        if (! $result['trusted']) {
            return response()->json([
                'passed' => true,
                'verified' => false,
                'message' => 'A böngészős ellenőrzés szerint jó a kimenet, de ez a régi ellenőrzéstípus nem ad XP-t. Az adminban állíts be szerveroldali HTML/CSS/JavaScript ellenőrzést.',
                'console_output' => $actual,
                'earned_xp' => 0,
                'already_completed' => (bool) $submission->completed_at,
                'completed_at' => $submission->completed_at,
                'is_completed' => (bool) $submission->completed_at,
                'xp_points' => (int) $request->user()->fresh()->xp_points,
                'unlocked_achievements' => [],
                ...$this->timingPayload($submission),
            ]);
        }

        $award = DB::transaction(function () use ($request, $project, $validated, $actual, $companionService): array {
            $submission = ProjectSubmission::query()
                ->where('user_id', $request->user()->id)
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertStarted($submission);
        $this->assertNotExpired($submission);

            $submission->html_code = $validated['html_code'] ?? '';
            $submission->css_code = $validated['css_code'] ?? '';
            $submission->javascript_code = $validated['javascript_code'] ?? '';
            $submission->last_console_output = implode("\n", $actual);

            if ($submission->completed_at) {
                $submission->save();
                return ['earned_xp' => 0, 'already_completed' => true, 'completed_at' => $submission->completed_at];
            }

            $reward = max(0, (int) $project->xp_reward);
            $submission->completed_at = now();
            $submission->xp_awarded = $reward;
            $submission->save();

            if ($reward > 0) $companionService->awardLearningPoints($request->user(), $reward);

            return ['earned_xp' => $reward, 'already_completed' => false, 'completed_at' => $submission->completed_at];
        });

        $unlocked = $award['already_completed']
            ? []
            : $learningExperience->recordLearningActivity($request->user()->fresh());

        $submission->refresh();

        return response()->json([
            'passed' => true,
            'verified' => true,
            'message' => $award['already_completed']
                ? 'A projekt már korábban teljesítve lett. A megoldásod frissítve.'
                : 'Sikeres projekt! Megkaptad a jutalmat, Pixel is fejlődött, a projekt pedig bekerült a portfóliódba.',
            'earned_xp' => $award['earned_xp'],
            'already_completed' => $award['already_completed'],
            'completed_at' => $award['completed_at'],
            'is_completed' => true,
            'xp_points' => (int) $request->user()->fresh()->xp_points,
            'unlocked_achievements' => $unlocked,
            ...$this->timingPayload($submission),
        ]);
    }

    private function submissionForUser(Request $request, Project $project): ProjectSubmission
    {
        return ProjectSubmission::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'project_id' => $project->id,
            ],
            [
                'html_code' => $project->starter_html ?? '',
                'css_code' => $project->starter_css ?? '',
                'javascript_code' => $project->starter_javascript ?? '',
            ]
        )->fresh();
    }

    private function timingPayload(?ProjectSubmission $submission): array
    {
        if (! $submission) {
            return [
                'timer_started' => false,
                'started_at' => null,
                'expires_at' => null,
                'remaining_seconds' => null,
                'is_expired' => false,
            ];
        }

        $completed = (bool) $submission->completed_at;
        $expiresAt = $submission->expires_at;
        $remaining = $expiresAt ? max(0, now()->diffInSeconds($expiresAt, false)) : null;
        $expired = ! $completed && $expiresAt !== null && $expiresAt->isPast();

        return [
            'timer_started' => $submission->started_at !== null,
            'started_at' => $submission->started_at,
            'expires_at' => $expiresAt,
            'remaining_seconds' => $completed ? 0 : $remaining,
            'is_expired' => $expired,
        ];
    }

    private function assertStarted(ProjectSubmission $submission): void
    {
        if ($submission->completed_at) return;
        if (! $submission->started_at) {
            throw new HttpResponseException(response()->json([
                'message' => 'Előbb nyomd meg a Kezdés gombot. Az idő csak ezután indul el.',
                'timer_started' => false,
            ], 409));
        }
    }

    private function assertNotExpired(ProjectSubmission $submission): void
    {
        if ($submission->completed_at) return;

        if ($submission->expires_at && $submission->expires_at->isPast()) {
            throw new HttpResponseException(response()->json([
                'message' => 'A projektre rendelkezésre álló idő lejárt. A szerkesztő zárolva van.',
                'is_expired' => true,
                'expires_at' => $submission->expires_at,
                'remaining_seconds' => 0,
            ], 423));
        }
    }
}
