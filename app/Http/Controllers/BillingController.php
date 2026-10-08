<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\StripeClient;
use Throwable;

class BillingController extends Controller
{
    public function plans()
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            return response()->json(['plans' => [], 'configured' => false]);
        }

        $stripe = new StripeClient($secret);
        $configured = [
            'monthly' => config('services.stripe.premium_monthly_price_id'),
            'yearly' => config('services.stripe.premium_yearly_price_id'),
        ];

        $plans = [];

        foreach ($configured as $key => $priceId) {
            if (! $priceId) {
                continue;
            }

            try {
                $price = $stripe->prices->retrieve($priceId, []);
                $plans[] = [
                    'key' => $key,
                    'price_id' => $price->id,
                    'unit_amount' => $price->unit_amount,
                    'currency' => strtoupper((string) $price->currency),
                    'interval' => $price->recurring?->interval,
                    'interval_count' => $price->recurring?->interval_count,
                ];
            } catch (Throwable) {
                // Hibás vagy még nem létrehozott Stripe Price esetén ne törjön el a pricing oldal.
            }
        }

        return response()->json([
            'plans' => $plans,
            'configured' => true,
        ]);
    }

    public function status(Request $request)
    {
        $user = $request->user()->fresh();

        return response()->json([
            'plan' => $user->plan,
            'subscription_status' => $user->subscription_status,
            'subscription_current_period_end' => $user->subscription_current_period_end,
            'is_premium' => $user->is_premium,
        ]);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'plan' => ['required', 'in:monthly,yearly'],
        ]);

        $priceId = match ($validated['plan']) {
            'monthly' => config('services.stripe.premium_monthly_price_id'),
            'yearly' => config('services.stripe.premium_yearly_price_id'),
        };

        if (! $priceId) {
            return response()->json([
                'message' => 'Ez a Premium csomag még nincs beállítva a Stripe-ban.',
            ], 503);
        }

        $user = $request->user()->fresh();

        if ($user->stripe_subscription_id
            && ! in_array($user->subscription_status, ['canceled', 'incomplete_expired'], true)) {
            return response()->json([
                'message' => 'Ehhez a fiókhoz már tartozik folyamatban lévő vagy aktív előfizetés. A módosításhoz használd a számlázási portált.',
            ], 409);
        }

        $stripe = $this->stripe();

        if (! $user->stripe_customer_id) {
            $customer = $stripe->customers->create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'getingo_user_id' => (string) $user->id,
                ],
            ]);

            $user->stripe_customer_id = $customer->id;
            $user->save();
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $session = $stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $user->stripe_customer_id,
            'client_reference_id' => (string) $user->id,
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'auto',
            'success_url' => $frontend.'/premium/siker?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontend.'/premium?cancelled=1',
            'metadata' => [
                'getingo_user_id' => (string) $user->id,
                'getingo_plan' => 'premium',
                'getingo_billing_cycle' => $validated['plan'],
            ],
            'subscription_data' => [
                'metadata' => [
                    'getingo_user_id' => (string) $user->id,
                    'getingo_plan' => 'premium',
                    'getingo_billing_cycle' => $validated['plan'],
                ],
            ],
        ]);

        return response()->json([
            'url' => $session->url,
        ]);
    }

    public function portal(Request $request)
    {
        $user = $request->user();

        if (! $user->stripe_customer_id) {
            return response()->json([
                'message' => 'Ehhez a fiókhoz még nem tartozik Stripe ügyfél.',
            ], 422);
        }

        $session = $this->stripe()->billingPortal->sessions->create([
            'customer' => $user->stripe_customer_id,
            'return_url' => rtrim((string) config('app.frontend_url'), '/').'/premium',
        ]);

        return response()->json([
            'url' => $session->url,
        ]);
    }

    private function stripe(): StripeClient
    {
        $secret = config('services.stripe.secret');

        abort_unless($secret, 503, 'A Stripe nincs konfigurálva.');

        return new StripeClient($secret);
    }
}
