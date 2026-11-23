<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'source', 'type', 'customer_name', 'customer_phone', 'customer_email', 'pickup_address', 'drop_address', 'pickup_at', 'return_at',
        'vehicle_category_id', 'passengers', 'distance_km', 'duration_min', 'rental_hours', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:2', 'commission_amount' => 'decimal:2',
            'type' => BookingType::class, 'status' => BookingStatus::class, 'payment_status' => PaymentStatus::class,
            'pickup_at' => 'datetime', 'return_at' => 'datetime', 'cancelled_at' => 'datetime', 'phone_verified' => 'boolean',
            'distance_km' => 'decimal:1', 'quoted_total' => 'decimal:2', 'final_total' => 'decimal:2', 'amount_paid' => 'decimal:2',
            'fare_snapshot' => 'array', 'final_snapshot' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'booking_number';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(BookingStop::class)->orderBy('sequence');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(BookingQuotation::class)->orderByDesc('version');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class)->orderByDesc('id');
    }

    public function trip(): HasOne
    {
        return $this->hasOne(Trip::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(BookingReview::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Amount the customer currently owes (final if completed, else quoted). */
    public function payable(): string
    {
        return (string) ($this->final_total ?? $this->quoted_total ?? '0.00');
    }

    public function balance(): string
    {
        return Money::sub($this->payable(), (string) $this->amount_paid);
    }
}
