<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Project;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100'
        ]);

        $query = mb_strtolower(trim($validated['q']));

        $lessons = Lesson::query()
            ->where('is_published', true)
            ->select('id', 'category_id', 'title', 'slug', 'content')
            ->where(function ($builder) use ($query) {
                $builder
                    ->whereRaw('LOWER(title) LIKE ?', ["%{$query}%"])
                    ->orWhereRaw('LOWER(content) LIKE ?', ["%{$query}%"]);
            })
            ->limit(20)
            ->get();

        $exercises = Exercise::query()
            ->select('id', 'category_id', 'title', 'description', 'difficulty')
            ->where(function ($builder) use ($query) {
                $builder
                    ->whereRaw('LOWER(title) LIKE ?', ["%{$query}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$query}%"]);
            })
            ->limit(20)
            ->get();

        $projects = Project::query()
            ->select('id', 'title', 'description', 'difficulty', 'estimated_time')
            ->where(function ($builder) use ($query) {
                $builder
                    ->whereRaw('LOWER(title) LIKE ?', ["%{$query}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$query}%"]);
            })
            ->limit(20)
            ->get();

        return response()->json([
            'query' => $validated['q'],
            'results' => [
                'lessons' => $lessons,
                'exercises' => $exercises,
                'projects' => $projects
            ]
        ]);
    }
}