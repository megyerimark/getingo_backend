<?php

namespace App\Http\Controllers;

use App\Models\StripeWebhookEvent;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');
        $stripeSecret = config('services.stripe.secret');

        if (! $secret || ! $stripeSecret) {
            return response()->json(['message' => 'A Stripe webhook nincs teljesen konfigurálva.'], 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret
            );
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            Log::warning('Stripe webhook ellenőrzés sikertelen.', ['message' => $exception->getMessage()]);
            return response()->json(['message' => 'Érvénytelen Stripe webhook.'], 400);
        }

        $eventRecord = StripeWebhookEvent::firstOrCreate(
            ['stripe_event_id' => (string) $event->id],
            ['type' => (string) $event->type]
        );

        if ($eventRecord->processed_at) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        try {
            $stripe = new StripeClient($stripeSecret);

            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object;
                    if (($session->mode ?? null) === 'subscription' && ! empty($session->subscription)) {
                        $this->syncSubscription($stripe->subscriptions->retrieve((string) $session->subscription, []));
                    }
                    break;

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    $subscriptionId = (string) ($event->data->object->id ?? '');
                    if ($subscriptionId !== '') {
                        // Mindig a Stripe jelenlegi állapotát olvassuk vissza. Így egy késve érkező
                        // régi webhook nem írhatja felül egy újabb subscription állapotát.
                        $this->syncSubscription($stripe->subscriptions->retrieve($subscriptionId, []));
                    }
                    break;

                case 'invoice.paid':
                case 'invoice.payment_failed':
                    $invoiceId = (string) ($event->data->object->id ?? '');
                    if ($invoiceId !== '') {
                        $invoice = $stripe->invoices->retrieve($invoiceId, []);
                        $this->syncInvoice($invoice, (string) ($invoice->status ?? '') === 'paid' ? 'paid' : 'failed');
                    }
                    break;
            }

            $eventRecord->forceFill(['processed_at' => now()])->save();
        } catch (Throwable $exception) {
            Log::error('Stripe webhook feldolgozás sikertelen.', [
                'stripe_event_id' => (string) $event->id,
                'type' => (string) $event->type,
                'message' => $exception->getMessage(),
            ]);

            // 5xx válaszra a Stripe újrapróbálja az eseményt. A rekord processed_at mezője
            // üres marad, ezért ugyanaz az esemény biztonságosan újrafeldolgozható.
            return response()->json(['message' => 'A Stripe esemény feldolgozása nem sikerült.'], 500);
        }

        return response()->json(['received' => true]);
    }

    private function syncSubscription($subscription): void
    {
        $user = User::query()
            ->where('stripe_customer_id', (string) $subscription->customer)
            ->orWhere('stripe_subscription_id', $subscription->id)
            ->first();

        if (! $user) {
            $userId = $subscription->metadata?->getingo_user_id ?? null;
            $user = $userId ? User::find($userId) : null;
        }

        if (! $user) return;

        $status = (string) $subscription->status;
        $premium = in_array($status, ['active', 'trialing'], true) && $this->containsPremiumPrice($subscription);
        $periodEnd = $subscription->items->data[0]->current_period_end ?? null;
        $billingCycle = $this->billingCycleForSubscription($subscription)
            ?? ($subscription->metadata?->getingo_billing_cycle ?? null)
            ?? $user->subscription_billing_cycle;

        $user->forceFill([
            'stripe_customer_id' => (string) $subscription->customer,
            'stripe_subscription_id' => $subscription->id,
            'subscription_status' => $status,
            'subscription_billing_cycle' => $premium ? $billingCycle : $user->subscription_billing_cycle,
            'subscription_current_period_end' => $periodEnd ? Carbon::createFromTimestampUTC((int) $periodEnd) : null,
            'premium_started_at' => $premium ? ($user->premium_started_at ?? now()) : $user->premium_started_at,
            'plan' => $premium ? 'premium' : 'free',
        ])->save();
    }

    private function syncInvoice($invoice, string $status): void
    {
        $customerId = is_string($invoice->customer ?? null) ? $invoice->customer : ($invoice->customer->id ?? null);
        $user = $customerId ? User::where('stripe_customer_id', $customerId)->first() : null;
        $subscriptionId = $this->subscriptionIdFromInvoice($invoice);
        if (! $user && $subscriptionId) $user = User::where('stripe_subscription_id', $subscriptionId)->first();

        SubscriptionPayment::updateOrCreate(
            ['stripe_invoice_id' => (string) $invoice->id],
            [
                'user_id' => $user?->id,
                'stripe_customer_id' => $customerId,
                'stripe_subscription_id' => $subscriptionId,
                'status' => $status,
                'amount_paid' => max(0, (int) ($invoice->amount_paid ?? 0)),
                'amount_due' => max(0, (int) ($invoice->amount_due ?? 0)),
                'currency' => strtoupper((string) ($invoice->currency ?? 'HUF')),
                'billing_reason' => $invoice->billing_reason ?? null,
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'paid_at' => $status === 'paid'
                    ? Carbon::createFromTimestampUTC((int) ($invoice->status_transitions?->paid_at ?? $invoice->created ?? now()->timestamp))
                    : null,
                'period_start' => ! empty($invoice->period_start) ? Carbon::createFromTimestampUTC((int) $invoice->period_start) : null,
                'period_end' => ! empty($invoice->period_end) ? Carbon::createFromTimestampUTC((int) $invoice->period_end) : null,
            ]
        );
    }

    private function containsPremiumPrice($subscription): bool
    {
        $allowed = array_filter([
            config('services.stripe.premium_monthly_price_id'),
            config('services.stripe.premium_yearly_price_id'),
        ]);

        foreach ($subscription->items->data as $item) {
            if (in_array($item->price->id ?? null, $allowed, true)) return true;
        }
        return false;
    }

    private function billingCycleForSubscription($subscription): ?string
    {
        foreach ($subscription->items->data as $item) {
            $priceId = $item->price->id ?? null;
            if ($priceId === config('services.stripe.premium_monthly_price_id')) return 'monthly';
            if ($priceId === config('services.stripe.premium_yearly_price_id')) return 'yearly';
        }
        return null;
    }

    private function subscriptionIdFromInvoice($invoice): ?string
    {
        $legacy = $invoice->subscription ?? null;
        if (is_string($legacy) && $legacy !== '') return $legacy;
        $modern = $invoice->parent?->subscription_details?->subscription ?? null;
        return is_string($modern) && $modern !== '' ? $modern : null;
    }
}
