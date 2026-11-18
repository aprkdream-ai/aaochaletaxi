<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingQuotation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['booking_id', 'version', 'breakdown', 'total', 'valid_until', 'sent_at', 'accepted_at'];

    protected function casts(): array
    {
        return ['breakdown' => 'array', 'total' => 'decimal:2', 'valid_until' => 'datetime', 'sent_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
