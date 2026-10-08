<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CurriculumStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_groups_lessons_into_ordered_sections(): void
    {
        $category = Category::create([
            'name' => 'JavaScript',
            'slug' => 'javascript',
            'sort_order' => 1,
        ]);

        $basics = LessonSection::create([
            'category_id' => $category->id,
            'name' => 'Alapok',
            'slug' => 'alapok',
            'sort_order' => 10,
        ]);

        $dom = LessonSection::create([
            'category_id' => $category->id,
            'name' => 'DOM',
            'slug' => 'dom',
            'sort_order' => 20,
        ]);

        Lesson::create([
            'category_id' => $category->id,
            'lesson_section_id' => $dom->id,
            'title' => 'DOM alapok',
            'slug' => 'dom-alapok',
            'sort_order' => 10,
            'content' => 'DOM',
        ]);

        Lesson::create([
            'category_id' => $category->id,
            'lesson_section_id' => $basics->id,
            'title' => 'Mi az a JavaScript?',
            'slug' => 'mi-az-a-javascript',
            'sort_order' => 10,
            'content' => 'JS',
        ]);

        $response = $this->getJson("/api/categories/{$category->id}/curriculum")
            ->assertOk();

        $response->assertJsonPath('category.name', 'JavaScript');
        $response->assertJsonPath('sections.0.name', 'Alapok');
        $response->assertJsonPath('sections.0.lessons.0.title', 'Mi az a JavaScript?');
        $response->assertJsonPath('sections.1.name', 'DOM');
    }

    public function test_curriculum_marks_authenticated_users_completed_lessons(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $category = Category::create([
            'name' => 'JavaScript',
            'slug' => 'javascript',
            'sort_order' => 1,
        ]);

        $section = LessonSection::create([
            'category_id' => $category->id,
            'name' => 'Alapok',
            'slug' => 'alapok',
            'sort_order' => 10,
        ]);

        $lesson = Lesson::create([
            'category_id' => $category->id,
            'lesson_section_id' => $section->id,
            'title' => 'console.log()',
            'slug' => 'console-log',
            'sort_order' => 20,
            'content' => 'Console',
        ]);

        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'completed' => true,
        ]);

        $this->getJson("/api/categories/{$category->id}/curriculum")
            ->assertOk()
            ->assertJsonPath('progress.completed', 1)
            ->assertJsonPath('progress.percentage', 100)
            ->assertJsonPath('sections.0.lessons.0.completed', true);
    }
}
