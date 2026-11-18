<?php

namespace App\Services\Finance;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SequenceService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GST invoices for completed bookings. Numbers are gap-free per operator and financial year and ≤ 16 characters
 * (GST rule 46). Issued invoices are immutable; corrections = credit note + new invoice.
 */
class InvoiceService
{
    public const SAC = '996601'; // Rental services of road vehicles with operator

    public function __construct(private SequenceService $sequences, private AuditLogger $audit) {}

    public static function prefix(Tenant $tenant): string
    {
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($tenant->settings['invoice_prefix'] ?? '')));

        if ($p === '') {
            $p = preg_replace('/[^A-Z0-9]/', '', strtoupper($tenant->slug)) ?: 'INV';
        }

        return substr($p, 0, 4);
    }

    public function activeInvoice(Booking $booking): ?Invoice
    {
        return Invoice::query()->where('booking_id', $booking->id)->where('type', 'invoice')->where('status', 'issued')->latest('id')->first();
    }

    /** @param array{name?:?string, gstin?:?string, address?:?string, state?:?string, inter_state?:bool} $buyer */
    public function issue(Booking $booking, User $user, array $buyer = []): Invoice
    {
        if ($booking->status !== BookingStatus::Completed || ! $booking->final_snapshot) {
            throw ValidationException::withMessages(['invoice' => __('Invoices can be issued for completed trips only.')]);
        }
        $tenant = Tenant::findOrFail($booking->tenant_id);
        $snap = $booking->final_snapshot;

        return DB::transaction(function () use ($booking, $user, $buyer, $tenant, $snap) {
            Booking::query()->whereKey($booking->id)->lockForUpdate()->first();
            if ($this->activeInvoice($booking)) {
                throw ValidationException::withMessages(['invoice' => __('An invoice already exists. Issue a credit note first to correct it.')]);
            }

            $fy = SequenceService::financialYear(now());
            $shortFy = substr($fy, 2); // 2026-27 → 26-27
            $number = sprintf('%s/%s/%04d', self::prefix($tenant), $shortFy, $this->sequences->next("invoice:{$tenant->id}:{$fy}"));

            $interState = (bool) ($buyer['inter_state'] ?? false);
            $gst = (string) $snap['gst'];
            $half = Money::round(bcdiv($gst, '2', 4));

            $invoice = new Invoice([
                'booking_id' => $booking->id, 'type' => 'invoice', 'number' => $number, 'status' => 'issued', 'invoice_date' => now()->toDateString(),
                'seller' => $this->seller($tenant),
                'buyer' => [
                    'name' => ($buyer['name'] ?? null) ?: $booking->customer_name, 'phone' => $booking->customer_phone, 'email' => $booking->customer_email,
                    'gstin' => isset($buyer['gstin']) ? strtoupper($buyer['gstin']) : null, 'address' => $buyer['address'] ?? null, 'state' => $buyer['state'] ?? null,
                ],
                'place_of_supply' => ($buyer['state'] ?? null) ?: $tenant->state, 'inter_state' => $interState,
                'taxable' => $snap['taxable'], 'gst_percent' => $snap['gst_percent'],
                'cgst' => $interState ? 0 : $half, 'sgst' => $interState ? 0 : Money::sub($gst, $half), 'igst' => $interState ? $gst : 0,
                'reimbursements' => $snap['passthrough_total'], 'round_off' => $snap['round_off'], 'total' => $snap['total'],
            ]);
            $invoice->created_by = $user->id;
            $invoice->save();

            $route = trim($booking->pickup_address.($booking->drop_address ? ' → '.$booking->drop_address : ''));
            foreach ($snap['lines'] as $line) {
                InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => $line['label'].($line['detail'] ? ' ('.$line['detail'].')' : ''), 'sac' => self::SAC, 'taxable' => true, 'amount' => $line['amount']]);
            }
            if ((float) $snap['discount'] > 0) {
                InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => __('Discount'), 'sac' => self::SAC, 'taxable' => true, 'amount' => '-'.$snap['discount']]);
            }
            foreach ($snap['passthrough'] as $line) {
                InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => $line['label'].' — '.__('reimbursement at actuals'), 'sac' => null, 'taxable' => false, 'amount' => $line['amount']]);
            }
            $invoice->forceFill(['notes' => mb_substr(__(':type · :route · :date', ['type' => $booking->type->label(), 'route' => $route, 'date' => $booking->pickup_at->format('d M Y')]), 0, 500)]);
            Invoice::withoutEvents(fn () => $invoice->save());

            $this->audit->log('invoice.issued', $invoice, [], ['number' => $number, 'total' => $invoice->total]);

            return $invoice;
        });
    }

    public function creditNote(Invoice $invoice, string $reason, User $user): Invoice
    {
        if ($invoice->isCreditNote() || $invoice->status !== 'issued') {
            throw ValidationException::withMessages(['invoice' => __('Only an issued invoice can be credited.')]);
        }
        $tenant = Tenant::findOrFail($invoice->tenant_id);

        return DB::transaction(function () use ($invoice, $reason, $user, $tenant) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $fy = SequenceService::financialYear(now());
            $number = sprintf('%sC/%s/%04d', substr(self::prefix($tenant), 0, 3), substr($fy, 2), $this->sequences->next("credit_note:{$tenant->id}:{$fy}"));

            $cn = new Invoice($locked->only(['booking_id', 'seller', 'buyer', 'place_of_supply', 'inter_state', 'taxable', 'gst_percent', 'cgst', 'sgst', 'igst', 'reimbursements', 'round_off', 'total']) + [
                'type' => 'credit_note', 'number' => $number, 'status' => 'issued', 'credit_note_for' => $locked->id,
                'invoice_date' => now()->toDateString(), 'notes' => mb_substr(__('Against :no — :r', ['no' => $locked->number, 'r' => $reason]), 0, 500),
            ]);
            $cn->created_by = $user->id;
            $cn->save();
            foreach ($locked->items as $item) {
                InvoiceItem::create(['invoice_id' => $cn->id, 'description' => $item->description, 'sac' => $item->sac, 'taxable' => $item->taxable, 'amount' => $item->amount]);
            }
            $locked->status = 'credited';
            $locked->save();
            $this->audit->log('invoice.credited', $locked, [], ['credit_note' => $number, 'reason' => $reason]);

            return $cn;
        });
    }

    private function seller(Tenant $tenant): array
    {
        return [
            'name' => $tenant->legal_name ?: $tenant->name, 'brand' => $tenant->name, 'gstin' => $tenant->gstin, 'pan' => $tenant->pan,
            'address' => collect([$tenant->address_line, $tenant->city, $tenant->state, $tenant->pincode])->filter()->join(', '),
            'state' => $tenant->state, 'phone' => $tenant->phone, 'email' => $tenant->email, 'logo' => $tenant->logo_path,
        ];
    }
}
