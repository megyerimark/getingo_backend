<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLessonController extends Controller
{
    public function index()
    {
        return response()->json(
            Lesson::query()
                ->leftJoin('lesson_sections', 'lessons.lesson_section_id', '=', 'lesson_sections.id')
                ->select('lessons.*')
                ->with([
                    'category:id,name',
                    'section:id,category_id,name,slug,sort_order',
                ])
                ->orderBy('lessons.category_id')
                ->orderByRaw('COALESCE(lesson_sections.sort_order, 999999)')
                ->orderBy('lessons.sort_order')
                ->orderBy('lessons.id')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));
        $lesson = Lesson::create($validated);

        return response()->json([
            'message' => 'A lecke sikeresen létrehozva!',
            'lesson' => $lesson->load(['category:id,name', 'section:id,category_id,name,slug,sort_order']),
        ], 201);
    }

    public function show(Lesson $lesson)
    {
        return response()->json(
            $lesson->load(['category:id,name', 'section:id,category_id,name,slug,sort_order'])
        );
    }

    public function update(Request $request, Lesson $lesson)
    {
        $validated = $request->validate($this->rules($request, $lesson));
        $lesson->update($validated);

        return response()->json([
            'message' => 'A lecke sikeresen frissítve!',
            'lesson' => $lesson->fresh()->load(['category:id,name', 'section:id,category_id,name,slug,sort_order']),
        ]);
    }

    public function destroy(Lesson $lesson)
    {
        $lesson->delete();

        return response()->json([
            'message' => 'A lecke sikeresen törölve!',
        ]);
    }

    private function rules(Request $request, ?Lesson $lesson = null): array
    {
        $categoryId = $request->integer('category_id');

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'lesson_section_id' => [
                'required',
                'integer',
                Rule::exists('lesson_sections', 'id')
                    ->where(fn ($query) => $query->where('category_id', $categoryId)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('lessons', 'slug')->ignore($lesson?->id),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_published' => ['required', 'boolean'],
            'content' => ['required', 'string'],
            'example_code' => ['nullable', 'string'],
            'example_html' => ['nullable', 'string', 'max:100000'],
            'example_css' => ['nullable', 'string', 'max:100000'],
            'example_javascript' => ['nullable', 'string', 'max:100000'],
        ];
    }
}
