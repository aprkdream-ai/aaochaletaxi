<?php

namespace App\Services\Payments;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\PaymentOrder;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\Booking\BookingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Online advance for a booking (collected on the platform's Razorpay account; settled to the operator later).
 * Idempotent: checkout callback and webhook may both arrive.
 */
class BookingCheckoutService
{
    public function __construct(private RazorpayGateway $gateway, private BookingService $bookings, private TenantContext $context, private AuditLogger $audit) {}

    public static function advancePercent(Tenant $tenant): int
    {
        return max(0, min(100, (int) ($tenant->settings['online_advance_percent'] ?? 0)));
    }

    public function advanceAmount(Booking $booking, Tenant $tenant): ?string
    {
        $pct = self::advancePercent($tenant);
        if ($pct === 0 || ! $booking->quoted_total) {
            return null;
        }
        $due = (float) $booking->quoted_total * $pct / 100 - (float) $booking->amount_paid;

        return $due >= 1 ? number_format(ceil($due), 2, '.', '') : null;
    }

    public function available(Booking $booking, Tenant $tenant): bool
    {
        return $this->gateway->isEnabled() && in_array($booking->status, [BookingStatus::Quoted, BookingStatus::Confirmed, BookingStatus::Assigned], true)
            && $this->advanceAmount($booking, $tenant) !== null;
    }

    public function start(Booking $booking, Tenant $tenant): PaymentOrder
    {
        if (! $this->available($booking, $tenant)) {
            throw ValidationException::withMessages(['payment' => __('Online payment is not available for this booking.')]);
        }
        $amount = $this->advanceAmount($booking, $tenant);
        $order = $this->gateway->createOrder((int) bcmul($amount, '100', 0), 'bk_'.$booking->id.'_'.now()->format('His'), [
            'booking' => $booking->booking_number, 'operator' => $tenant->name,
        ]);

        return $this->context->runAs($tenant, function () use ($booking, $amount, $order) {
            $po = new PaymentOrder([
                'purpose' => 'booking', 'amount' => $amount, 'tax_amount' => 0, 'total' => $amount, 'currency' => 'INR',
                'gateway' => 'razorpay', 'gateway_mode' => $this->gateway->mode(), 'gateway_order_id' => $order['id'],
            ]);
            $po->booking_id = $booking->id;
            $po->save();

            return $po;
        });
    }

    public function fulfil(string $gatewayOrderId, string $gatewayPaymentId, string $via, ?int $paidAmountPaise = null): bool
    {
        return DB::transaction(function () use ($gatewayOrderId, $gatewayPaymentId, $via, $paidAmountPaise) {
            $order = PaymentOrder::withoutGlobalScopes()->where('gateway_order_id', $gatewayOrderId)->lockForUpdate()->first();
            if (! $order || $order->status === 'paid') {
                return false;
            }
            if ($paidAmountPaise !== null && $paidAmountPaise !== $order->amountInPaise()) {
                $order->forceFill(['status' => 'failed', 'failure_reason' => "Amount mismatch: {$paidAmountPaise}"])->save();
                Log::error('Razorpay booking amount mismatch', ['order' => $gatewayOrderId]);

                return false;
            }

            $tenant = Tenant::findOrFail($order->tenant_id);
            $this->context->runAs($tenant, function () use ($order, $gatewayPaymentId, $via) {
                $booking = Booking::query()->findOrFail($order->booking_id);
                $this->bookings->recordPayment($booking, 'advance', $order->total, 'razorpay', $gatewayPaymentId, null, __('Paid online (:mode) — collected by AaoChaleTaxi, settled to operator', ['mode' => $order->gateway_mode]));
                if ($booking->status === BookingStatus::Quoted) {
                    $this->bookings->confirm($booking->refresh(), null);
                }
                $order->forceFill(['status' => 'paid', 'gateway_payment_id' => $gatewayPaymentId, 'fulfilled_via' => $via, 'paid_at' => now()])->save();
                $this->audit->log('payment.booking_online', $booking, [], ['amount' => $order->total, 'via' => $via], $booking->tenant_id);
            });

            return true;
        });
    }
}
