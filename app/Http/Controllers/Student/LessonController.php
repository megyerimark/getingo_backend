<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonSection;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function index($categoryId)
    {
        $lessons = Lesson::query()
            ->where('category_id', $categoryId)
            ->where('is_published', true)
            ->with('section:id,category_id,name,sort_order')
            ->orderBy('lesson_section_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Lesson $lesson) => $this->lessonPayload($lesson));

        return response()->json($lessons);
    }

    public function curriculum(Request $request, $categoryId)
    {
        $category = Category::query()
            ->select('id', 'name', 'slug', 'sort_order')
            ->findOrFail($categoryId);

        $user = auth('sanctum')->user();
        $completedLessonIds = $user
            ? LessonProgress::query()
                ->where('user_id', $user->id)
                ->where('completed', true)
                ->whereHas('lesson', fn ($query) => $query->where('category_id', $category->id))
                ->pluck('lesson_id')
                ->all()
            : [];

        $completedLookup = array_fill_keys($completedLessonIds, true);
        $favoriteLookup = $user
            ? array_fill_keys(Favorite::query()->where('user_id', $user->id)->pluck('lesson_id')->all(), true)
            : [];

        $sections = LessonSection::query()
            ->where('category_id', $category->id)
            ->with(['lessons' => fn ($query) => $query
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (LessonSection $section) use ($completedLookup, $favoriteLookup) {
                $lessons = $section->lessons->map(function (Lesson $lesson) use ($completedLookup, $favoriteLookup) {
                    return [
                        ...$this->lessonPayload($lesson),
                        'completed' => isset($completedLookup[$lesson->id]),
                        'is_favorite' => isset($favoriteLookup[$lesson->id]),
                    ];
                })->values();

                $completed = $lessons->where('completed', true)->count();
                $total = $lessons->count();

                return [
                    'id' => $section->id,
                    'category_id' => $section->category_id,
                    'name' => $section->name,
                    'slug' => $section->slug,
                    'description' => $section->description,
                    'sort_order' => $section->sort_order,
                    'progress' => [
                        'completed' => $completed,
                        'total' => $total,
                        'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
                    ],
                    'lessons' => $lessons,
                ];
            })
            ->values();

        $total = $sections->sum(fn (array $section) => $section['progress']['total']);
        $completed = $sections->sum(fn (array $section) => $section['progress']['completed']);

        return response()->json([
            'category' => $category,
            'progress' => [
                'completed' => $completed,
                'total' => $total,
                'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            ],
            'sections' => $sections,
        ]);
    }

    private function lessonPayload(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'category_id' => $lesson->category_id,
            'lesson_section_id' => $lesson->lesson_section_id,
            'sort_order' => $lesson->sort_order,
            'is_published' => (bool) $lesson->is_published,
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'content' => $lesson->content,
            'example_code' => $lesson->example_code,
            'example_html' => $lesson->example_html,
            'example_css' => $lesson->example_css,
            'example_javascript' => $lesson->example_javascript,
        ];
    }
}
