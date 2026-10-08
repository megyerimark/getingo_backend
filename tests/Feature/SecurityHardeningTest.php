<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_banned_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'banned@example.com',
            'password' => 'StrongPassword123',
            'is_banned' => true,
        ]);

        $this->postJson('/api/bejelentkezes', [
            'email' => 'banned@example.com',
            'password' => 'StrongPassword123',
        ])->assertForbidden();
    }

    public function test_student_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_user_cannot_read_another_users_note(): void
    {
        $category = Category::create([
            'name' => 'HTML',
            'slug' => 'html',
            'sort_order' => 1,
        ]);

        $lesson = Lesson::create([
            'category_id' => $category->id,
            'title' => 'Teszt lecke',
            'slug' => 'teszt-lecke',
            'content' => 'Tartalom',
        ]);

        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $note = Note::create([
            'user_id' => $owner->id,
            'lesson_id' => $lesson->id,
            'content' => 'Privát jegyzet',
        ]);

        Sanctum::actingAs($attacker);

        $this->getJson('/api/notes/'.$note->id)->assertForbidden();
    }

    public function test_public_search_does_not_expose_project_solution(): void
    {
        Project::create([
            'title' => 'Laravel projekt',
            'description' => 'Teszt projekt',
            'difficulty' => 'kezdő',
            'estimated_time' => 60,
            'solution' => 'SECRET_SOLUTION',
        ]);

        $response = $this->getJson('/api/search?q=Laravel')
            ->assertOk();

        $this->assertStringNotContainsString(
            'SECRET_SOLUTION',
            $response->getContent()
        );
    }

    public function test_gdpr_account_deletion_removes_user_and_related_notes(): void
    {
        $category = Category::create([
            'name' => 'CSS',
            'slug' => 'css',
            'sort_order' => 2,
        ]);

        $lesson = Lesson::create([
            'category_id' => $category->id,
            'title' => 'CSS lecke',
            'slug' => 'css-lecke',
            'content' => 'Tartalom',
        ]);

        $user = User::factory()->create([
            'password' => 'StrongPassword123',
            'role' => 'student',
        ]);

        $note = Note::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'content' => 'Törlendő',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/gdpr/delete-account', [
            'password' => 'StrongPassword123',
        ])->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }
    public function test_unpublished_lessons_are_not_exposed_by_public_endpoints(): void
    {
        $category = Category::create([
            'name' => 'Piszkozat teszt',
            'slug' => 'piszkozat-teszt',
            'sort_order' => 10,
        ]);

        Lesson::create([
            'category_id' => $category->id,
            'title' => 'Publikált lecke',
            'slug' => 'publikalt-lecke',
            'content' => 'Nyilvános tartalom',
            'sort_order' => 10,
            'is_published' => true,
        ]);

        Lesson::create([
            'category_id' => $category->id,
            'title' => 'Titkos piszkozat',
            'slug' => 'titkos-piszkozat',
            'content' => 'SecretDraftContent',
            'sort_order' => 20,
            'is_published' => false,
        ]);

        $this->getJson('/api/categories/'.$category->id.'/lessons')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Publikált lecke');

        $this->getJson('/api/search?q=SecretDraftContent')
            ->assertOk()
            ->assertJsonCount(0, 'results.lessons');
    }

    public function test_subscription_fields_are_not_mass_assignable(): void
    {
        $fillable = (new User())->getFillable();

        $this->assertNotContains('plan', $fillable);
        $this->assertNotContains('subscription_status', $fillable);
        $this->assertNotContains('stripe_customer_id', $fillable);
        $this->assertNotContains('stripe_subscription_id', $fillable);
    }

}
