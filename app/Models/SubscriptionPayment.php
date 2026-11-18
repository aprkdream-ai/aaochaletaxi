<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only: never update amounts after creation; record a new row instead. */
class SubscriptionPayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'subscription_id', 'invoice_number', 'amount', 'tax_percent', 'tax_amount', 'total', 'method', 'reference',
        'gateway', 'gateway_order_id', 'gateway_payment_id', 'status', 'paid_at', 'billing_snapshot', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'billing_snapshot' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class)->withoutGlobalScopes();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
