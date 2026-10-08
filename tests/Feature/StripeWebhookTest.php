<?php

namespace Tests\Feature;

use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_already_processed_stripe_event_is_idempotent(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_getingo');
        config()->set('services.stripe.secret', 'sk_test_getingo');

        $eventId = 'evt_getingo_duplicate_1';
        StripeWebhookEvent::create([
            'stripe_event_id' => $eventId,
            'type' => 'customer.subscription.updated',
            'processed_at' => now(),
        ]);

        $payload = json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => ['id' => 'sub_should_not_be_fetched']],
        ], JSON_THROW_ON_ERROR);

        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_getingo');

        $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            ],
            $payload
        )->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, StripeWebhookEvent::where('stripe_event_id', $eventId)->count());
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_getingo');
        config()->set('services.stripe.secret', 'sk_test_getingo');

        $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.now()->timestamp.',v1=invalid',
            ],
            '{"id":"evt_invalid","object":"event","type":"invoice.paid","data":{"object":{}}}'
        )->assertBadRequest();
    }
}
