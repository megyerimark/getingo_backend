<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $search = trim((string) $request->query('search', ''));

        return response()->json(
            Category::query()
                ->withCount(['lessons', 'lessonSections', 'exercises'])
                ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                }))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $category = Category::create($validated);

        AuditLogger::record($request, 'category.created', $category);

        return response()->json([
            'message' => 'Kategória sikeresen létrehozva!',
            'category' => $category,
        ], 201);
    }

    public function show(Category $category)
    {
        return response()->json(
            $category->loadCount(['lessons', 'exercises'])
        );
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate($this->rules($category, true));
        $category->update($validated);

        AuditLogger::record($request, 'category.updated', $category, [
            'changed_fields' => array_keys($validated),
        ]);

        return response()->json([
            'message' => 'Kategória sikeresen frissítve!',
            'category' => $category->fresh(),
        ]);
    }

    public function destroy(Request $request, Category $category)
    {
        if ($category->lessons()->exists() || $category->exercises()->exists()) {
            return response()->json([
                'message' => 'A kategória nem törölhető, mert tartozik hozzá lecke vagy feladat.',
            ], 409);
        }

        AuditLogger::record($request, 'category.deleted', $category);
        $category->delete();

        return response()->json([
            'message' => 'Kategória sikeresen törölve!',
        ]);
    }

    private function rules(?Category $category = null, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'slug' => [
                $presence,
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'sort_order' => [$presence, 'integer', 'min:0', 'max:10000'],
        ];
    }
}