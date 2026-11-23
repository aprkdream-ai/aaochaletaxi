<?php

namespace App\Models;

use App\Enums\BusinessType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'price_monthly', 'price_yearly', 'addon_price_monthly', 'addon_price_yearly', 'max_vehicles',
        'max_drivers', 'max_branches', 'max_users', 'features', 'advanced_reports', 'trial_days', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'addon_price_monthly' => 'decimal:2',
            'addon_price_yearly' => 'decimal:2',
            'features' => 'array',
            'advanced_reports' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function basePrice(string $cycle): string
    {
        return number_format((float) ($cycle === 'yearly' ? $this->price_yearly : $this->price_monthly), 2, '.', '');
    }

    /** Add-on charged for the second module (Taxi + Fleet); zero for single-module businesses. */
    public function addonPrice(string $cycle, ?BusinessType $type): string
    {
        if (! $type?->hasAddon()) {
            return '0.00';
        }

        return number_format((float) ($cycle === 'yearly' ? $this->addon_price_yearly : $this->addon_price_monthly), 2, '.', '');
    }

    /** Total price (excl. GST) for a business type: single source for admin activation, checkout and price displays. */
    public function priceFor(string $cycle, ?BusinessType $type): string
    {
        return Money::add($this->basePrice($cycle), $this->addonPrice($cycle, $type));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
