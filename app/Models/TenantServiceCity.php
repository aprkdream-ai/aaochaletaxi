<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** City an operator serves on the marketplace (queried across tenants by city_key; written only via the tenant). */
class TenantServiceCity extends Model
{
    protected $fillable = ['city'];

    protected static function booted(): void
    {
        static::saving(fn (self $c) => $c->city_key = self::key($c->city));
    }

    public static function key(?string $city): string
    {
        return Str::slug((string) $city);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
