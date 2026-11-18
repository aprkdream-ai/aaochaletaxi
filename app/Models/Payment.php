<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only. Corrections are recorded as a new row with reversal_of set. */
class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['booking_id', 'kind', 'amount', 'method', 'reference', 'paid_at', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Payments are append-only; record a reversal instead.'));
        static::deleting(fn () => throw new \LogicException('Payments are append-only; record a reversal instead.'));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
