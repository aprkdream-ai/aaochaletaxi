<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** tenant_id NULL = platform default. Operators see defaults + their own custom categories. */
class VehicleCategory extends Model
{
    protected $fillable = ['name', 'slug', 'seats', 'icon', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeAvailableTo(Builder $q, ?int $tenantId): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('tenant_id')->when($tenantId, fn ($x) => $x->orWhere('tenant_id', $tenantId)))
            ->orderBy('sort_order')->orderBy('name');
    }

    public function isPlatformDefault(): bool
    {
        return $this->tenant_id === null;
    }

    public const DEFAULTS = [
        ['Hatchback', 'hatchback', 4, 'car-front'],
        ['Sedan', 'sedan', 4, 'car-front-fill'],
        ['SUV', 'suv', 6, 'truck-front'],
        ['MUV', 'muv', 7, 'truck-front-fill'],
        ['Premium', 'premium', 4, 'star'],
        ['Tempo Traveller', 'tempo-traveller', 12, 'bus-front'],
        ['Bus', 'bus', 20, 'bus-front-fill'],
    ];
}
