<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\Project;
use App\Models\ProjectSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_lesson_starts_streak_and_unlocks_achievement(): void
    {
        $category = Category::create([
            'name' => 'JavaScript',
            'slug' => 'javascript',
            'sort_order' => 1,
        ]);

        $lesson = Lesson::create([
            'category_id' => $category->id,
            'title' => 'Első lecke',
            'slug' => 'elso-lecke-wow',
            'content' => 'Tartalom',
        ]);

        $user = User::factory()->create(['current_streak' => 0]);
        Sanctum::actingAs($user);

        $this->postJson('/api/progress', ['lesson_id' => $lesson->id])
            ->assertOk()
            ->assertJsonPath('unlocked_achievements.0.slug', 'first-lesson');

        $user->refresh();
        $this->assertSame(1, $user->current_streak);
        $this->assertSame(1, $user->longest_streak);
    }

    public function test_dashboard_returns_next_lesson_and_learning_path(): void
    {
        $category = Category::create([
            'name' => 'JavaScript',
            'slug' => 'javascript',
            'sort_order' => 1,
        ]);

        $lesson = Lesson::create([
            'category_id' => $category->id,
            'title' => 'Következő lecke',
            'slug' => 'kovetkezo-lecke-wow',
            'content' => 'Tartalom',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('next_lesson.id', $lesson->id)
            ->assertJsonPath('learning_path.0.name', 'JavaScript')
            ->assertJsonPath('daily_goals.0.key', 'lesson')
            ->assertJsonPath('companion.companion.selected_skin', 'code-kitten-3d')
            ->assertJsonStructure(['notes', 'companion']);
    }

    public function test_completed_project_is_visible_in_own_portfolio(): void
    {
        $project = Project::create([
            'title' => 'Portfólió projekt',
            'description' => 'Teszt',
            'difficulty' => 'kezdő',
            'estimated_time' => 15,
            'xp_reward' => 25,
        ]);

        $user = User::factory()->create();
        ProjectSubmission::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'html_code' => '<h1>Szia</h1>',
            'css_code' => 'h1 { font-weight: bold; }',
            'javascript_code' => "console.log('Szia');",
            'completed_at' => now(),
            'xp_awarded' => 25,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('projects.0.title', 'Portfólió projekt');

        $this->assertStringNotContainsString('solution', $response->getContent());
        $this->assertStringNotContainsString('expected_output', $response->getContent());
    }
}
