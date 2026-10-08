<?php

namespace Tests\Feature;

use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_subscription_and_revenue_summaries(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $customer = User::factory()->create([
            'plan' => 'premium',
            'subscription_status' => 'active',
            'subscription_billing_cycle' => 'monthly',
            'stripe_customer_id' => 'cus_test_1',
            'stripe_subscription_id' => 'sub_test_1',
            'premium_started_at' => now()->subDays(10),
            'email_verified_at' => now(),
        ]);

        SubscriptionPayment::create([
            'user_id' => $customer->id,
            'stripe_invoice_id' => 'in_test_1',
            'stripe_customer_id' => 'cus_test_1',
            'stripe_subscription_id' => 'sub_test_1',
            'status' => 'paid',
            'amount_paid' => 249900,
            'amount_due' => 249900,
            'currency' => 'HUF',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/admin/subscriptions')
            ->assertOk()
            ->assertJsonPath('summary.active', 1)
            ->assertJsonPath('subscriptions.0.email', $customer->email);

        $this->actingAs($admin)
            ->getJson('/api/admin/revenue')
            ->assertOk()
            ->assertJsonPath('summary.total_revenue', 249900)
            ->assertJsonPath('summary.active_subscriptions', 1);
    }
}
