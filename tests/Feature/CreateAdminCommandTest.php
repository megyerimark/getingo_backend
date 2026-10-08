<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_user_can_be_promoted_to_verified_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@getingo.test',
            'role' => 'student',
            'email_verified_at' => null,
        ]);

        $this->artisan('getingo:create-admin', [
            'email' => $user->email,
            '--promote' => true,
        ])->assertSuccessful();

        $user->refresh();
        $this->assertSame('admin', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse((bool) $user->is_banned);
    }
}
