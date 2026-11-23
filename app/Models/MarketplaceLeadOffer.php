<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceLeadOffer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['marketplace_lead_id', 'status', 'estimate'];

    protected function casts(): array
    {
        return ['estimate' => 'decimal:2', 'responded_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketplaceLead::class, 'marketplace_lead_id');
    }
}
