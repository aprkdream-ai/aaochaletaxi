<?php

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'legal_name', 'email', 'phone', 'gstin', 'pan', 'address_line',
        'city', 'state', 'pincode', 'logo_path', 'primary_color',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'kyc_status' => KycStatus::class,
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            $tenant->uuid ??= (string) Str::uuid();
            $tenant->status ??= TenantStatus::Pending;
            $tenant->kyc_status ??= KycStatus::NotSubmitted;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /** Subscriptions bypass the tenant scope here: the relation itself constrains tenant_id. */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->withoutGlobalScopes()->latest('id');
    }

    /** Most recent subscription row (any status). */
    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->withoutGlobalScopes()->latestOfMany('id');
    }

    /** Latest subscription that currently grants access (trialing / active / grace). */
    public function usableSubscription(): ?Subscription
    {
        $usable = array_values(array_map(
            fn (SubscriptionStatus $s) => $s->value,
            array_filter(SubscriptionStatus::cases(), fn (SubscriptionStatus $s) => $s->isUsable())
        ));

        return $this->subscriptions()->with('plan')->whereIn('status', $usable)->first();
    }

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->oldestOfMany('id');
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'operator';
        $base = Str::limit($base, 60, '');
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, static::RESERVED_SLUGS, true)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public const RESERVED_SLUGS = [
        'admin', 'app', 'api', 'account', 'driver', 'login', 'logout', 'register', 'o', 'www',
        'about', 'services', 'contact', 'book', 'build', 'storage',
    ];
}
