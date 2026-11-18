<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $table = 'invoices';

    protected $fillable = ['booking_id', 'type', 'number', 'credit_note_for', 'status', 'invoice_date', 'seller', 'buyer', 'place_of_supply', 'inter_state', 'taxable', 'gst_percent', 'cgst', 'sgst', 'igst', 'reimbursements', 'round_off', 'total', 'notes'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'seller' => 'array', 'buyer' => 'array', 'inter_state' => 'boolean', 'taxable' => 'decimal:2', 'gst_percent' => 'decimal:2', 'cgst' => 'decimal:2', 'sgst' => 'decimal:2', 'igst' => 'decimal:2', 'reimbursements' => 'decimal:2', 'round_off' => 'decimal:2', 'total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        // Issued documents are immutable; only the status may change (issued → credited).
        static::updating(function (Invoice $i) {
            if (array_diff(array_keys($i->getDirty()), ['status', 'updated_at'])) {
                throw new \LogicException('Invoices are immutable. Issue a credit note instead.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Invoices cannot be deleted.'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credit_note_for');
    }

    public function isCreditNote(): bool
    {
        return $this->type === 'credit_note';
    }

    public function title(): string
    {
        return match (true) {
            $this->isCreditNote() => __('Credit note'),
            filled($this->seller['gstin'] ?? null) => __('Tax invoice'),
            default => __('Invoice'),
        };
    }

    public function tax(): string
    {
        return Money::add((string) $this->cgst, (string) $this->sgst, (string) $this->igst);
    }
}
