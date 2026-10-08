<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_endpoint_returns_categories_and_latest_lessons_in_one_request(): void
    {
        $category = Category::create([
            'name' => 'JavaScript',
            'slug' => 'javascript',
            'sort_order' => 1,
        ]);

        $lesson = Lesson::create([
            'category_id' => $category->id,
            'title' => 'Első JavaScript lecke',
            'slug' => 'elso-javascript-lecke',
            'content' => 'Teszt tartalom',
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('categories.0.id', $category->id)
            ->assertJsonPath('categories.0.lessons_count', 1)
            ->assertJsonPath('latest_lessons.0.id', $lesson->id)
            ->assertJsonPath('latest_lessons.0.category_name', 'JavaScript');
    }
}
