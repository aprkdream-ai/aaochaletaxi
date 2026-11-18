<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStop extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'booking_stops';

    protected $fillable = ['booking_id', 'sequence', 'address'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
