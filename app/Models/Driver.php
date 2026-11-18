<?php

namespace App\Models;

use App\Enums\DriverStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Expiry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Driver extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'branch_id', 'name', 'phone', 'alt_phone', 'email', 'licence_number', 'licence_type', 'licence_expiry', 'aadhaar_last4',
        'date_of_birth', 'joined_on', 'address', 'pay_type', 'pay_amount', 'status', 'is_available', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DriverStatus::class, 'is_available' => 'boolean', 'licence_expiry' => 'date',
            'date_of_birth' => 'date', 'joined_on' => 'date', 'pay_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(FleetDocument::class, 'documentable')->latest('id');
    }

    public function licence(): array
    {
        return Expiry::of($this->licence_expiry);
    }

    public function isAssignable(): bool
    {
        return $this->status === DriverStatus::Active && $this->is_available && $this->licence()['state'] !== 'expired';
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }
}
