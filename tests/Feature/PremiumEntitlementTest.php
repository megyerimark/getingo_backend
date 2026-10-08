<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremiumEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscription_is_premium(): void
    {
        $user = User::factory()->create([
            'plan' => 'premium',
            'subscription_status' => 'active',
        ]);

        $this->assertTrue($user->is_premium);
    }

    public function test_canceled_subscription_is_not_premium(): void
    {
        $user = User::factory()->create([
            'plan' => 'premium',
            'subscription_status' => 'canceled',
        ]);

        $this->assertFalse($user->is_premium);
    }
}
