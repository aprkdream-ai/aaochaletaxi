<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['invoice_id', 'description', 'sac', 'taxable', 'amount'];

    protected function casts(): array
    {
        return ['taxable' => 'boolean', 'amount' => 'decimal:2'];
    }
}
