<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_requires_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'regi@example.com',
            'password' => 'StrongPassword123',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/account', [
            'name' => $user->name,
            'email' => 'uj@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertSame('regi@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_change_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'regi@example.com',
            'password' => 'StrongPassword123',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/account', [
            'name' => $user->name,
            'email' => 'uj@example.com',
            'current_password' => 'WrongPassword123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertSame('regi@example.com', $user->fresh()->email);
    }

    public function test_email_change_with_correct_password_resets_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'regi@example.com',
            'password' => 'StrongPassword123',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/account', [
            'name' => 'Teszt Felhasználó',
            'email' => 'UJ@EXAMPLE.COM',
            'current_password' => 'StrongPassword123',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'uj@example.com')
            ->assertJsonPath('user.email_verified_at', null);

        $user->refresh();
        $this->assertSame('uj@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_password_change_updates_password_without_logging_out_current_request(): void
    {
        $user = User::factory()->create([
            'password' => 'StrongPassword123',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/account/password', [
            'current_password' => 'StrongPassword123',
            'password' => 'NewStrongPassword456',
            'password_confirmation' => 'NewStrongPassword456',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewStrongPassword456', $user->fresh()->password));
        $this->getJson('/api/user')->assertOk();
    }
}
