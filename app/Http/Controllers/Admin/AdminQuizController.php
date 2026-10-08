<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AdminQuizController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $lessonId = $request->integer('lesson_id');
        $correctAnswer = strtolower(trim((string) $request->query('correct_answer', '')));

        $items = Quiz::query()
            ->with('lesson:id,title,slug')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('question', 'like', "%{$search}%")
                        ->orWhere('option_a', 'like', "%{$search}%")
                        ->orWhere('option_b', 'like', "%{$search}%")
                        ->orWhere('option_c', 'like', "%{$search}%")
                        ->orWhere('option_d', 'like', "%{$search}%")
                        ->orWhereHas('lesson', fn ($lesson) => $lesson->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($lessonId > 0, fn ($query) => $query->where('lesson_id', $lessonId))
            ->when(in_array($correctAnswer, ['a', 'b', 'c', 'd'], true), fn ($query) => $query->where('correct_answer', $correctAnswer))
            ->latest()
            ->paginate($perPage);

        $items->getCollection()->transform(fn (Quiz $quiz) => $quiz->makeVisible('correct_answer'));
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $quiz = Quiz::create($validated)->makeVisible('correct_answer');

        AuditLogger::record($request, 'quiz.created', $quiz);

        return response()->json([
            'message' => 'Kvíz sikeresen hozzáadva a leckéhez!',
            'quiz' => $quiz,
        ], 201);
    }

    public function show(Quiz $quiz)
    {
        return response()->json($quiz->load('lesson:id,title,slug')->makeVisible('correct_answer'));
    }

    public function update(Request $request, Quiz $quiz)
    {
        $validated = $request->validate($this->rules(true));
        $quiz->update($validated);

        AuditLogger::record($request, 'quiz.updated', $quiz, [
            'changed_fields' => array_keys($validated),
        ]);

        return response()->json([
            'message' => 'A kvíz sikeresen frissítve!',
            'quiz' => $quiz->fresh()->makeVisible('correct_answer'),
        ]);
    }

    public function destroy(Request $request, Quiz $quiz)
    {
        AuditLogger::record($request, 'quiz.deleted', $quiz);
        $quiz->delete();

        return response()->json([
            'message' => 'A kvíz sikeresen törölve!',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'lesson_id' => [$presence, 'integer', 'exists:lessons,id'],
            'question' => [$presence, 'string', 'max:1000'],
            'option_a' => [$presence, 'string', 'max:1000'],
            'option_b' => [$presence, 'string', 'max:1000'],
            'option_c' => [$presence, 'string', 'max:1000'],
            'option_d' => [$presence, 'string', 'max:1000'],
            'correct_answer' => [$presence, 'in:a,b,c,d'],
        ];
    }
}
