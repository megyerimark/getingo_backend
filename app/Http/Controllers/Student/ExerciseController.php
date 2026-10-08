<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseSubmission;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $difficulty = trim((string) $request->query('difficulty', ''));
        $userId = $request->user()->id;

        $items = Exercise::query()
            ->with('category:id,name,slug')
            ->with(['submissions' => fn ($q) => $q->where('user_id', $userId)])
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($difficulty !== '', fn ($q) => $q->where('difficulty', $difficulty))
            ->orderBy('category_id')->orderBy('id')->get();

        return response()->json($items->map(fn (Exercise $exercise) => $this->payload(
            $exercise,
            $exercise->submissions->first()
        ))->values());
    }

    public function show(Request $request, Exercise $exercise): JsonResponse
    {
        $submission = ExerciseSubmission::firstOrCreate(
            ['user_id' => $request->user()->id, 'exercise_id' => $exercise->id],
            [
                'answer' => '',
                'started_at' => now(),
                'expires_at' => now()->addMinutes(max(1, (int) $exercise->estimated_time)),
            ]
        );

        if (! $submission->started_at) {
            $submission->forceFill([
                'started_at' => now(),
                'expires_at' => now()->addMinutes(max(1, (int) $exercise->estimated_time)),
            ])->save();
        }

        return response()->json($this->payload($exercise->load('category:id,name,slug'), $submission->fresh()));
    }

    public function saveWorkspace(Request $request, Exercise $exercise): JsonResponse
    {
        $validated = $request->validate(['answer' => ['nullable', 'string', 'max:200000']]);
        $submission = ExerciseSubmission::firstOrCreate(
            ['user_id' => $request->user()->id, 'exercise_id' => $exercise->id],
            [
                'answer' => '',
                'started_at' => now(),
                'expires_at' => now()->addMinutes(max(1, (int) $exercise->estimated_time)),
            ]
        );

        if ($submission->expires_at?->isPast()) {
            throw new HttpResponseException(response()->json([
                'message' => 'A feladatra rendelkezésre álló idő lejárt.',
                'is_expired' => true,
            ], 423));
        }

        $submission->update(['answer' => $validated['answer'] ?? '']);

        return response()->json([
            'message' => 'A megoldásod elmentve.',
            'submission' => $this->submissionPayload($submission->fresh()),
        ]);
    }

    private function payload(Exercise $exercise, ?ExerciseSubmission $submission): array
    {
        return [
            'id' => $exercise->id,
            'category_id' => $exercise->category_id,
            'title' => $exercise->title,
            'description' => $exercise->description,
            'difficulty' => $exercise->difficulty,
            'estimated_time' => (int) $exercise->estimated_time,
            'category' => $exercise->category,
            'submission' => $submission ? $this->submissionPayload($submission) : null,
        ];
    }

    private function submissionPayload(ExerciseSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'answer' => $submission->answer ?? '',
            'started_at' => $submission->started_at,
            'expires_at' => $submission->expires_at,
            'completed_at' => $submission->completed_at,
            'is_expired' => ! $submission->completed_at && (bool) $submission->expires_at?->isPast(),
        ];
    }
}
