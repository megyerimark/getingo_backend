<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonSection;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLessonSectionController extends Controller
{
    public function index(Request $request)
    {
        $query = LessonSection::query()
            ->with('category:id,name')
            ->withCount('lessons')
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $section = LessonSection::create($validated);

        AuditLogger::record($request, 'lesson_section.created', $section);

        return response()->json([
            'message' => 'A fejezet sikeresen létrehozva!',
            'section' => $section->load('category:id,name')->loadCount('lessons'),
        ], 201);
    }

    public function show(LessonSection $lessonSection)
    {
        return response()->json(
            $lessonSection->load('category:id,name')->loadCount('lessons')
        );
    }

    public function update(Request $request, LessonSection $lessonSection)
    {
        $validated = $request->validate($this->rules($lessonSection));
        $lessonSection->update($validated);

        AuditLogger::record($request, 'lesson_section.updated', $lessonSection, [
            'changed_fields' => array_keys($validated),
        ]);

        return response()->json([
            'message' => 'A fejezet sikeresen frissítve!',
            'section' => $lessonSection->fresh()->load('category:id,name')->loadCount('lessons'),
        ]);
    }

    public function destroy(Request $request, LessonSection $lessonSection)
    {
        if ($lessonSection->lessons()->exists()) {
            return response()->json([
                'message' => 'A fejezet nem törölhető, amíg leckék tartoznak hozzá.',
            ], 409);
        }

        AuditLogger::record($request, 'lesson_section.deleted', $lessonSection);
        $lessonSection->delete();

        return response()->json([
            'message' => 'A fejezet sikeresen törölve!',
        ]);
    }

    private function rules(?LessonSection $section = null): array
    {
        $categoryId = request()->integer('category_id') ?: $section?->category_id;

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('lesson_sections', 'slug')
                    ->where(fn ($query) => $query->where('category_id', $categoryId))
                    ->ignore($section?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
