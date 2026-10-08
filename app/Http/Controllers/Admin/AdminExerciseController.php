<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AdminExerciseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->integer('category_id');
        $difficulty = trim((string) $request->query('difficulty', ''));

        $items = Exercise::query()
            ->with('category:id,name,slug')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($difficulty !== '', fn ($query) => $query->where('difficulty', $difficulty))
            ->latest()
            ->paginate($perPage);

        $items->getCollection()->transform(fn (Exercise $exercise) => $exercise->makeVisible('solution'));
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $exercise = Exercise::create($validated)->makeVisible('solution');

        AuditLogger::record($request, 'exercise.created', $exercise);

        return response()->json([
            'message' => 'A feladat sikeresen létrehozva!',
            'exercise' => $exercise,
        ], 201);
    }

    public function show(Exercise $exercise)
    {
        return response()->json($exercise->load('category:id,name,slug')->makeVisible('solution'));
    }

    public function update(Request $request, Exercise $exercise)
    {
        $validated = $request->validate($this->rules(true));
        $exercise->update($validated);

        AuditLogger::record($request, 'exercise.updated', $exercise, [
            'changed_fields' => array_keys($validated),
        ]);

        return response()->json([
            'message' => 'A feladat sikeresen frissítve!',
            'exercise' => $exercise->fresh()->makeVisible('solution'),
        ]);
    }

    public function destroy(Request $request, Exercise $exercise)
    {
        AuditLogger::record($request, 'exercise.deleted', $exercise);
        $exercise->delete();

        return response()->json([
            'message' => 'A feladat sikeresen törölve!',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'category_id' => [$presence, 'integer', 'exists:categories,id'],
            'title' => [$presence, 'string', 'max:255'],
            'description' => [$presence, 'string', 'max:100000'],
            'difficulty' => [$presence, 'string', 'max:50'],
            'estimated_time' => [$presence, 'integer', 'min:1', 'max:1440'],
            'solution' => ['nullable', 'string', 'max:200000'],
        ];
    }
}
