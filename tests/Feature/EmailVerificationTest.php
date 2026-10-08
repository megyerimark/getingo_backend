<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

    $response = $this->postJson('/api/regisztracio', [
    'name' => 'Teszt Elek',
    'email' => 'teszt@example.com',
    'password' => 'StrongPassword123',
    'password_confirmation' => 'StrongPassword123',
    'privacy_accepted' => true,
])->assertCreated();

        $user = User::where('email', 'teszt@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_unverified_user_cannot_open_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_signed_link_verifies_email(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $this->get($url)->assertRedirect(
            rtrim((string) config('app.frontend_url'), '/').'/email-verified'
        );

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_user_can_request_another_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/email/verification-notification')
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }
}
