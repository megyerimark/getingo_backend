<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;
use Throwable;

class AdminBillingController extends Controller
{
    public function subscriptions(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'all'));

        $query = User::query()
            ->where(function ($query) {
                $query->whereNotNull('stripe_customer_id')
                    ->orWhere('plan', 'premium')
                    ->orWhereNotNull('subscription_status');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('stripe_customer_id', 'like', "%{$search}%")
                        ->orWhere('stripe_subscription_id', 'like', "%{$search}%");
                });
            })
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'premium') {
                    $query->where('plan', 'premium')
                        ->whereIn('subscription_status', ['active', 'trialing']);
                    return;
                }

                $query->where('subscription_status', $status);
            })
            ->orderByRaw("CASE WHEN plan = 'premium' THEN 0 ELSE 1 END")
            ->orderByDesc('premium_started_at')
            ->orderByDesc('updated_at');

        $rows = $query->get([
            'id',
            'name',
            'email',
            'plan',
            'stripe_customer_id',
            'stripe_subscription_id',
            'subscription_status',
            'subscription_billing_cycle',
            'subscription_current_period_end',
            'premium_started_at',
            'created_at',
        ])->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'plan' => $user->plan,
            'is_premium' => $user->is_premium,
            'status' => $user->subscription_status,
            'billing_cycle' => $user->subscription_billing_cycle,
            'current_period_end' => $user->subscription_current_period_end,
            'premium_started_at' => $user->premium_started_at,
            'stripe_customer_id' => $user->stripe_customer_id,
            'stripe_subscription_id' => $user->stripe_subscription_id,
            'created_at' => $user->created_at,
        ]);

        $all = User::query()
            ->where(function ($query) {
                $query->whereNotNull('stripe_customer_id')
                    ->orWhereNotNull('subscription_status')
                    ->orWhere('plan', 'premium');
            });

        return response()->json([
            'summary' => [
                'total' => (clone $all)->count(),
                'active' => (clone $all)->where('subscription_status', 'active')->count(),
                'trialing' => (clone $all)->where('subscription_status', 'trialing')->count(),
                'past_due' => (clone $all)->where('subscription_status', 'past_due')->count(),
                'canceled' => (clone $all)->whereIn('subscription_status', ['canceled', 'unpaid', 'incomplete_expired'])->count(),
            ],
            'subscriptions' => $rows,
        ]);
    }

    public function revenue(Request $request)
    {
        $months = max(3, min(24, (int) $request->query('months', 12)));
        $start = now()->startOfMonth()->subMonths($months - 1);
        $payments = SubscriptionPayment::query()
            ->with('user:id,name,email')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->orderBy('paid_at')
            ->get();

        $series = collect(CarbonPeriod::create($start, '1 month', now()->startOfMonth()))
            ->map(function (Carbon $month) use ($payments) {
                $matched = $payments->filter(fn (SubscriptionPayment $payment) =>
                    $payment->paid_at?->format('Y-m') === $month->format('Y-m')
                );

                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->locale('hu')->translatedFormat('Y. M'),
                    'amount' => (int) $matched->sum('amount_paid'),
                    'payments' => $matched->count(),
                ];
            })->values();

        $currentMonth = SubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()])
            ->sum('amount_paid');

        $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $previousMonthStart->copy()->endOfMonth();
        $previousMonth = SubscriptionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount_paid');

        $growth = $previousMonth > 0
            ? round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1)
            : ($currentMonth > 0 ? 100.0 : 0.0);

        $priceAmounts = $this->priceAmounts();
        $activeUsers = User::query()
            ->where('plan', 'premium')
            ->whereIn('subscription_status', ['active', 'trialing'])
            ->get(['subscription_billing_cycle']);

        $mrr = $activeUsers->sum(function (User $user) use ($priceAmounts) {
            if ($user->subscription_billing_cycle === 'yearly') {
                return (int) round(($priceAmounts['yearly'] ?? 0) / 12);
            }

            return (int) ($priceAmounts['monthly'] ?? 0);
        });

        $recent = SubscriptionPayment::query()
            ->with('user:id,name,email')
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (SubscriptionPayment $payment) => [
                'id' => $payment->id,
                'user' => $payment->user ? [
                    'id' => $payment->user->id,
                    'name' => $payment->user->name,
                    'email' => $payment->user->email,
                ] : null,
                'status' => $payment->status,
                'amount_paid' => $payment->amount_paid,
                'currency' => $payment->currency,
                'billing_reason' => $payment->billing_reason,
                'paid_at' => $payment->paid_at,
                'hosted_invoice_url' => $payment->hosted_invoice_url,
            ]);

        return response()->json([
            'summary' => [
                'total_revenue' => (int) SubscriptionPayment::query()->where('status', 'paid')->sum('amount_paid'),
                'current_month_revenue' => (int) $currentMonth,
                'previous_month_revenue' => (int) $previousMonth,
                'month_growth_percentage' => $growth,
                'mrr' => (int) $mrr,
                'active_subscriptions' => $activeUsers->count(),
                'successful_payments' => SubscriptionPayment::query()->where('status', 'paid')->count(),
                'failed_payments' => SubscriptionPayment::query()->where('status', 'failed')->count(),
                'currency' => 'HUF',
            ],
            'plans' => [
                'monthly' => [
                    'active' => User::query()->where('plan', 'premium')->whereIn('subscription_status', ['active', 'trialing'])->where('subscription_billing_cycle', 'monthly')->count(),
                    'unit_amount' => $priceAmounts['monthly'] ?? null,
                ],
                'yearly' => [
                    'active' => User::query()->where('plan', 'premium')->whereIn('subscription_status', ['active', 'trialing'])->where('subscription_billing_cycle', 'yearly')->count(),
                    'unit_amount' => $priceAmounts['yearly'] ?? null,
                ],
            ],
            'monthly' => $series,
            'recent_payments' => $recent,
            'last_synced_payment_at' => SubscriptionPayment::query()->max('updated_at'),
        ]);
    }


    public function exportRevenue(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? 'all'));
        $rows = SubscriptionPayment::query()
            ->with('user:id,name,email')
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('stripe_invoice_id', 'like', "%{$search}%")
                    ->orWhere('billing_reason', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when(! empty($validated['from']), fn ($q) => $q->whereDate('paid_at', '>=', $validated['from']))
            ->when(! empty($validated['to']), fn ($q) => $q->whereDate('paid_at', '<=', $validated['to']))
            ->orderByDesc('paid_at')->limit(20000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Dátum', 'Név', 'Email', 'Állapot', 'Összeg (fillér)', 'Pénznem', 'Számla ID', 'Indok'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    optional($row->paid_at)->format('Y-m-d H:i:s'),
                    $row->user?->name,
                    $row->user?->email,
                    $row->status,
                    $row->amount_paid,
                    $row->currency,
                    $row->stripe_invoice_id,
                    $row->billing_reason,
                ], ';');
            }
            fclose($out);
        }, 'getingo-bevetelek-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function syncStripe()
    {
        $stripe = $this->stripe();
        $startTimestamp = now()->subYear()->startOfDay()->timestamp;
        $startingAfter = null;
        $synced = 0;

        do {
            $params = [
                'limit' => 100,
                'created' => ['gte' => $startTimestamp],
            ];

            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $page = $stripe->invoices->all($params);

            foreach ($page->data as $invoice) {
                $this->upsertInvoice($invoice);
                $synced++;
            }

            $pageData = $page->data;
            $last = end($pageData);
            $startingAfter = $page->has_more && $last ? $last->id : null;
        } while ($startingAfter);

        return response()->json([
            'message' => 'Stripe bevételi adatok szinkronizálva.',
            'synced' => $synced,
        ]);
    }

    private function upsertInvoice($invoice): void
    {
        $customerId = is_string($invoice->customer ?? null)
            ? $invoice->customer
            : ($invoice->customer->id ?? null);
        $subscriptionId = $this->subscriptionIdFromInvoice($invoice);
        $user = $customerId ? User::where('stripe_customer_id', $customerId)->first() : null;
        if (! $user && $subscriptionId) {
            $user = User::where('stripe_subscription_id', $subscriptionId)->first();
        }
        $status = (string) ($invoice->status ?? 'open');

        if ($status === 'paid') {
            $normalizedStatus = 'paid';
        } elseif (in_array($status, ['uncollectible', 'void'], true)) {
            $normalizedStatus = 'failed';
        } else {
            $normalizedStatus = $status;
        }

        SubscriptionPayment::updateOrCreate(
            ['stripe_invoice_id' => (string) $invoice->id],
            [
                'user_id' => $user?->id,
                'stripe_customer_id' => $customerId,
                'stripe_subscription_id' => $subscriptionId,
                'status' => $normalizedStatus,
                'amount_paid' => max(0, (int) ($invoice->amount_paid ?? 0)),
                'amount_due' => max(0, (int) ($invoice->amount_due ?? 0)),
                'currency' => strtoupper((string) ($invoice->currency ?? 'HUF')),
                'billing_reason' => $invoice->billing_reason ?? null,
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'paid_at' => ! empty($invoice->status_transitions?->paid_at)
                    ? Carbon::createFromTimestampUTC((int) $invoice->status_transitions->paid_at)
                    : null,
                'period_start' => ! empty($invoice->period_start)
                    ? Carbon::createFromTimestampUTC((int) $invoice->period_start)
                    : null,
                'period_end' => ! empty($invoice->period_end)
                    ? Carbon::createFromTimestampUTC((int) $invoice->period_end)
                    : null,
            ]
        );
    }

    private function subscriptionIdFromInvoice($invoice): ?string
    {
        $legacy = $invoice->subscription ?? null;
        if (is_string($legacy) && $legacy !== '') {
            return $legacy;
        }

        $modern = $invoice->parent?->subscription_details?->subscription ?? null;
        if (is_string($modern) && $modern !== '') {
            return $modern;
        }

        return null;
    }

    private function priceAmounts(): array
    {
        return Cache::remember('admin:stripe:price-amounts', now()->addHour(), function () {
            try {
                $stripe = $this->stripe();
                $result = [];

                foreach ([
                    'monthly' => config('services.stripe.premium_monthly_price_id'),
                    'yearly' => config('services.stripe.premium_yearly_price_id'),
                ] as $key => $priceId) {
                    if (! $priceId) {
                        continue;
                    }
                    $result[$key] = (int) $stripe->prices->retrieve($priceId, [])->unit_amount;
                }

                return $result;
            } catch (Throwable) {
                return [];
            }
        });
    }

    private function stripe(): StripeClient
    {
        $secret = config('services.stripe.secret');
        abort_unless($secret, 503, 'A Stripe nincs konfigurálva.');

        return new StripeClient($secret);
    }
}
