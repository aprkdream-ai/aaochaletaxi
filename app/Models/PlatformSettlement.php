<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Platform → operator payout of advances collected online (admin-level, not tenant-scoped). */
class PlatformSettlement extends Model
{
    protected $fillable = ['tenant_id', 'gross', 'fee', 'net', 'payments_count', 'method', 'reference', 'paid_on', 'notes'];

    protected function casts(): array
    {
        return ['gross' => 'decimal:2', 'fee' => 'decimal:2', 'net' => 'decimal:2', 'paid_on' => 'date'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'platform_settlement_items')->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
