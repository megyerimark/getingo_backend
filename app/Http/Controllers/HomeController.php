<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->select('id', 'name', 'slug', 'sort_order')
            ->withCount(['lessons', 'lessonSections'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $latestLessons = Lesson::query()
            ->select('id', 'category_id', 'title', 'slug', 'content', 'created_at')
            ->with('category:id,name,slug')
            ->latest('created_at')
            ->latest('id')
            ->limit(3)
            ->get()
            ->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'category_id' => $lesson->category_id,
                'category_name' => $lesson->category?->name ?? 'Tananyag',
                'title' => $lesson->title,
                'slug' => $lesson->slug,
                'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($lesson->content))), 180, '…'),
                'created_at' => $lesson->created_at,
            ])
            ->values();

        return response()->json([
            'categories' => $categories,
            'latest_lessons' => $latestLessons,
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
