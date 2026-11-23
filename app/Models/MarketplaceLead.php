<?php

namespace App\Models;

use App\Enums\BookingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Platform-level customer request (not tenant-owned); operators see it only through their own offer. */
class MarketplaceLead extends Model
{
    protected $fillable = [
        'customer_name', 'customer_phone', 'customer_email', 'type', 'vehicle_category_id', 'from_city', 'to_city', 'pickup_address', 'drop_address',
        'pickup_at', 'return_at', 'rental_hours', 'passengers', 'distance_km', 'notes',
    ];

    protected function casts(): array
    {
        return ['type' => BookingType::class, 'pickup_at' => 'datetime', 'return_at' => 'datetime', 'expires_at' => 'datetime', 'distance_km' => 'decimal:1'];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function offers(): HasMany
    {
        return $this->hasMany(MarketplaceLeadOffer::class)->withoutGlobalScopes();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function acceptedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'accepted_tenant_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScopes();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->expires_at->isFuture();
    }
}
