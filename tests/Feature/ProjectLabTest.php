<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectLabTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_project_does_not_expose_solution_or_expected_output(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'title' => 'JS változó',
            'description' => 'Írj ki két értéket.',
            'difficulty' => 'kezdő',
            'estimated_time' => 15,
            'solution' => 'SECRET_SOLUTION',
            'starter_javascript' => 'let value = 18;',
            'validation_type' => 'javascript_contains',
            'expected_output' => "console.log(18)\nconsole.log(19)",
            'xp_reward' => 30,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/projects/'.$project->id)
            ->assertOk()
            ->assertJsonPath('project.validation_configured', true)
            ->assertJsonPath('project.xp_reward', 30);

        $this->assertStringNotContainsString('SECRET_SOLUTION', $response->getContent());
        $this->assertStringNotContainsString('console.log(18)', $response->getContent());
        $this->assertStringNotContainsString('console.log(19)', $response->getContent());
    }

    public function test_project_can_be_saved_and_completed_only_once_for_xp(): void
    {
        $user = User::factory()->create(['xp_points' => 0]);
        $project = Project::create([
            'title' => 'Console projekt',
            'description' => 'Kimenet ellenőrzés',
            'difficulty' => 'kezdő',
            'estimated_time' => 20,
            'validation_type' => 'javascript_contains',
            'expected_output' => "console.log(18)\nconsole.log(19)",
            'xp_reward' => 40,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/projects/'.$project->id.'/start')->assertOk()->assertJsonPath('timer_started', true);

        $this->putJson('/api/projects/'.$project->id.'/workspace', [
            'javascript_code' => 'console.log(18); console.log(19);',
        ])->assertOk();

        $this->postJson('/api/projects/'.$project->id.'/check', [
            'javascript_code' => 'console.log(18); console.log(19);',
            'console_output' => ['18', '19'],
        ])->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('earned_xp', 40);

        $this->assertSame(40, (int) $user->fresh()->xp_points);
        $this->assertNotNull(ProjectSubmission::first()->completed_at);

        $this->postJson('/api/projects/'.$project->id.'/check', [
            'javascript_code' => 'console.log(18); console.log(19);',
            'console_output' => ['18', '19'],
        ])->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('earned_xp', 0)
            ->assertJsonPath('already_completed', true);

        $this->assertSame(40, (int) $user->fresh()->xp_points);
    }

    public function test_wrong_server_checked_source_does_not_complete_project(): void
    {
        $user = User::factory()->create(['xp_points' => 0]);
        $project = Project::create([
            'title' => 'Hibás kimenet',
            'description' => 'Teszt',
            'difficulty' => 'kezdő',
            'estimated_time' => 10,
            'validation_type' => 'javascript_contains',
            'expected_output' => 'console.log(\"OK\")',
            'xp_reward' => 20,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/projects/'.$project->id.'/start')->assertOk();

        $this->postJson('/api/projects/'.$project->id.'/check', [
            'javascript_code' => 'console.log(\"NOPE\");',
            'console_output' => ['OK'],
        ])->assertOk()
            ->assertJsonPath('passed', false);

        $this->assertSame(0, (int) $user->fresh()->xp_points);
        $this->assertNull(ProjectSubmission::first()->completed_at);
    }
    public function test_forged_client_console_output_cannot_award_xp(): void
    {
        $user = User::factory()->create(['xp_points' => 0]);
        $project = Project::create([
            'title' => 'Régi konzolos projekt',
            'description' => 'Kliensoldali visszajelzés',
            'difficulty' => 'kezdő',
            'estimated_time' => 10,
            'validation_type' => 'console_exact',
            'expected_output' => 'SECRET',
            'xp_reward' => 100,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/projects/'.$project->id.'/start')->assertOk();

        $this->postJson('/api/projects/'.$project->id.'/check', [
            'javascript_code' => '',
            'console_output' => ['SECRET'],
        ])->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('verified', false)
            ->assertJsonPath('earned_xp', 0)
            ->assertJsonPath('is_completed', false);

        $this->assertSame(0, (int) $user->fresh()->xp_points);
        $this->assertNull(ProjectSubmission::first()->completed_at);
    }

    public function test_javascript_project_accepts_different_variable_names_and_values_when_structure_matches(): void
    {
        $user = User::factory()->create(['xp_points' => 0]);
        $project = Project::create([
            'title' => 'Rugalmas változók',
            'description' => 'Változók és kiírás',
            'difficulty' => 'kezdő',
            'estimated_time' => 20,
            'validation_type' => 'javascript_contains',
            'expected_output' => "let nev = \"9\";\nlet szuletesiEv = 1995;\nlet eletkor = 2026 - szuletesiEv;\nconsole.log(nev + eletkor);",
            'xp_reward' => 25,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/projects/'.$project->id.'/start')->assertOk();

        $this->postJson('/api/projects/'.$project->id.'/check', [
            'javascript_code' => "let felhasznalo = 'Anna';\nlet ev = 2001;\nlet kor = 2030 - ev;\nconsole.log(felhasznalo, kor);",
            'console_output' => ['Anna 29'],
        ])->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('verified', true)
            ->assertJsonPath('earned_xp', 25);
    }

    public function test_expired_project_cannot_be_modified_or_checked(): void
    {
        $user = User::factory()->create(['xp_points' => 0]);
        $project = Project::create([
            'title' => 'Időzített projekt',
            'description' => 'Teszt',
            'difficulty' => 'kezdő',
            'estimated_time' => 10,
            'validation_type' => 'javascript_contains',
            'expected_output' => 'console.log("OK")',
            'xp_reward' => 20,
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/projects/'.$project->id)
            ->assertOk()
            ->assertJsonPath('project.timer_started', false);

        $this->putJson('/api/projects/'.$project->id.'/workspace', [
            'javascript_code' => 'console.log("OK");',
        ])->assertStatus(409)->assertJsonPath('timer_started', false);

        $this->postJson('/api/projects/'.$project->id.'/start')
            ->assertOk()
            ->assertJsonPath('timer_started', true);

        $submission = ProjectSubmission::firstOrFail();
        $submission->expires_at = now()->subSecond();
        $submission->save();

        $this->putJson('/api/projects/'.$project->id.'/workspace', [
            'javascript_code' => 'console.log("OK");',
        ])->assertStatus(423)->assertJsonPath('is_expired', true);

        $this->postJson('/api/projects/'.$project->id.'/check', [
            'javascript_code' => 'console.log("OK");',
            'console_output' => ['OK'],
        ])->assertStatus(423)->assertJsonPath('is_expired', true);

        $this->postJson('/api/projects/'.$project->id.'/restart')
            ->assertOk()
            ->assertJsonPath('timer_started', true)
            ->assertJsonPath('is_expired', false);

        $this->putJson('/api/projects/'.$project->id.'/workspace', [
            'javascript_code' => 'console.log("OK");',
        ])->assertOk();

        $this->assertSame(0, (int) $user->fresh()->xp_points);
    }

}
