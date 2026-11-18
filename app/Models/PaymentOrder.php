<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = ['purpose', 'plan_id', 'billing_cycle', 'amount', 'tax_amount', 'total', 'currency', 'gateway', 'gateway_mode', 'gateway_order_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function amountInPaise(): int
    {
        return (int) bcmul((string) $this->total, '100', 0);
    }
}
