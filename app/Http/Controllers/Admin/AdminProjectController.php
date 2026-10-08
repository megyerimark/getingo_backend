<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\AuditLogger;
use App\Services\ProjectValidationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminProjectController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $difficulty = trim((string) $request->query('difficulty', ''));
        $validationType = trim((string) $request->query('validation_type', ''));

        $items = Project::query()
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($difficulty !== '', fn ($query) => $query->where('difficulty', $difficulty))
            ->when($validationType !== '', fn ($query) => $query->where('validation_type', $validationType))
            ->latest()
            ->paginate($perPage);

        $items->getCollection()->transform(
            fn (Project $project) => $project->makeVisible(['solution', 'expected_output'])
        );

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $project = Project::create($validated)->makeVisible(['solution', 'expected_output']);

        AuditLogger::record($request, 'project.created', $project);

        return response()->json([
            'message' => 'A projekt sikeresen létrehozva!',
            'project' => $project,
        ], 201);
    }

    public function show(Project $project)
    {
        return response()->json($project->makeVisible(['solution', 'expected_output']));
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate($this->rules(true));
        $project->update($validated);

        AuditLogger::record($request, 'project.updated', $project, [
            'changed_fields' => array_keys($validated),
        ]);

        return response()->json([
            'message' => 'A projekt sikeresen frissítve!',
            'project' => $project->fresh()->makeVisible(['solution', 'expected_output']),
        ]);
    }

    public function destroy(Request $request, Project $project)
    {
        AuditLogger::record($request, 'project.deleted', $project);
        $project->delete();

        return response()->json([
            'message' => 'A projekt sikeresen törölve!',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'description' => [$presence, 'string', 'max:100000'],
            'difficulty' => [$presence, 'string', 'max:50'],
            'estimated_time' => [$presence, 'integer', 'min:1', 'max:10080'],
            'solution' => ['nullable', 'string', 'max:200000'],
            'starter_html' => ['nullable', 'string', 'max:200000'],
            'starter_css' => ['nullable', 'string', 'max:200000'],
            'starter_javascript' => ['nullable', 'string', 'max:200000'],
            'validation_type' => [
                $partial ? 'sometimes' : 'required',
                'string',
                Rule::in(array_merge(ProjectValidationService::CLIENT_VALIDATION_TYPES, ProjectValidationService::SERVER_VALIDATION_TYPES)),
            ],
            'expected_output' => ['nullable', 'string', 'max:100000'],
            'xp_reward' => [$partial ? 'sometimes' : 'required', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
