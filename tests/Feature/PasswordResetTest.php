<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_sends_notification_without_exposing_accounts(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'mark@example.com']);

        $existing = $this->postJson('/api/password/forgot', ['email' => 'MARK@example.com']);
        $missing = $this->postJson('/api/password/forgot', ['email' => 'nincs@example.com']);

        $existing->assertOk()->assertJsonStructure(['message']);
        $missing->assertOk()->assertJsonStructure(['message']);
        $this->assertSame($existing->json('message'), $missing->json('message'));
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'mark@example.com']);
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => 'MARK@example.com',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'mark@example.com']);

        $this->postJson('/api/password/reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
