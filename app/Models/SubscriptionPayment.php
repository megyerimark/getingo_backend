<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'user_id',
        'stripe_invoice_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'status',
        'amount_paid',
        'amount_due',
        'currency',
        'billing_reason',
        'hosted_invoice_url',
        'paid_at',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'integer',
            'amount_due' => 'integer',
            'paid_at' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
