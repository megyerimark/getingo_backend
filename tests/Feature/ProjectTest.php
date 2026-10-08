<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_student_can_list_projects_without_solutions(): void
    {
        Project::create([
            'title' => 'Mini portfólió',
            'description' => 'Építs egy egyszerű portfólió oldalt.',
            'difficulty' => 'kezdő',
            'estimated_time' => 90,
            'solution' => 'SECRET_PROJECT_SOLUTION',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('projects.0.title', 'Mini portfólió');

        $this->assertStringNotContainsString('SECRET_PROJECT_SOLUTION', $response->getContent());
    }

    public function test_verified_student_can_open_project_without_solution(): void
    {
        $project = Project::create([
            'title' => 'Todo app',
            'description' => 'Készíts egy feladatkezelőt.',
            'difficulty' => 'közepes',
            'estimated_time' => 120,
            'solution' => 'HIDDEN_SOLUTION',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/projects/'.$project->id)
            ->assertOk()
            ->assertJsonPath('project.id', $project->id)
            ->assertJsonPath('project.title', 'Todo app');

        $this->assertStringNotContainsString('HIDDEN_SOLUTION', $response->getContent());
    }

    public function test_unverified_student_cannot_open_projects(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create());

        $this->getJson('/api/projects')->assertForbidden();
    }
}
