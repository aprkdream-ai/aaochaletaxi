<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Human-friendly labels, icons and colour tones for audit events (dashboards / activity feeds). */
class AuditPresenter
{
    /** prefix => [icon, tone] */
    private const GROUPS = [
        'auth' => ['box-arrow-in-right', 'secondary'],
        'tenant' => ['building', 'info'],
        'subscription' => ['credit-card-2-front', 'primary'],
        'payment' => ['cash-coin', 'success'],
        'kyc' => ['patch-check', 'info'],
        'support' => ['life-preserver', 'warning'],
        'plan' => ['tags', 'primary'],
        'platform' => ['gear', 'secondary'],
        'staff' => ['person-gear', 'info'],
        'role' => ['shield-lock', 'info'],
        'branch' => ['diagram-3', 'info'],
        'enquiry' => ['inbox', 'warning'],
        'booking' => ['journal-text', 'primary'],
        'vehicle' => ['car-front', 'primary'],
        'driver' => ['person-badge', 'success'],
        'fleet' => ['file-earmark-text', 'secondary'],
        'fare_rule' => ['calculator', 'secondary'],
        'invoice' => ['receipt', 'success'],
        'expense' => ['wallet2', 'warning'],
    ];

    /** Exact events that deserve their own icon/tone. */
    private const EVENTS = [
        'tenant.registered' => ['building-add', 'info'],
        'tenant.approved' => ['patch-check-fill', 'success'],
        'tenant.rejected' => ['x-octagon', 'danger'],
        'tenant.suspended' => ['slash-circle', 'danger'],
        'tenant.business_type_changed' => ['layers', 'info'],
        'subscription.activated' => ['check2-circle', 'success'],
        'subscription.cancelled' => ['x-circle', 'danger'],
        'subscription.payment_recorded' => ['cash-coin', 'success'],
        'payment.online_received' => ['cash-coin', 'success'],
        'payment.booking_online' => ['cash-coin', 'success'],
        'platform.integration_tested' => ['plug', 'info'],
        'platform.settlement' => ['bank', 'success'],
        'booking.created' => ['journal-plus', 'primary'],
        'booking.cancelled' => ['x-circle', 'danger'],
        'booking.completed' => ['flag-fill', 'success'],
        'booking.assigned' => ['person-check', 'info'],
        'booking.payment_advance' => ['cash-coin', 'success'],
        'booking.payment_balance' => ['cash-coin', 'success'],
        'booking.payment_refund' => ['arrow-counterclockwise', 'danger'],
        'driver.settled' => ['wallet2', 'success'],
        'driver.app_access_enabled' => ['phone', 'success'],
        'driver.app_access_disabled' => ['phone', 'secondary'],
        'invoice.credited' => ['receipt-cutoff', 'warning'],
        'vehicle.maintenance_added' => ['tools', 'warning'],
    ];

    private const LABELS = [
        'auth.login' => 'Signed in',
        'tenant.registered' => 'New operator registered',
        'tenant.approved' => 'Operator approved',
        'tenant.business_type_changed' => 'Business type changed',
        'booking.payment_advance' => 'Advance payment received',
        'booking.payment_balance' => 'Balance payment received',
        'booking.payment_refund' => 'Refund issued',
        'driver.settled' => 'Driver payout made',
        'driver.app_access_enabled' => 'Driver app access enabled',
        'driver.app_access_disabled' => 'Driver app access disabled',
        'platform.integration_tested' => 'Integration tested',
        'platform.settings_updated' => 'Platform settings updated',
        'platform.settlement' => 'Operator settlement paid',
        'payment.online_received' => 'Online subscription payment',
        'payment.booking_online' => 'Online booking payment',
        'invoice.credited' => 'Credit note issued',
    ];

    public static function label(string $event): string
    {
        return __(self::LABELS[$event] ?? Str::ucfirst(str_replace(['.', '_'], ' ', $event)));
    }

    public static function icon(string $event): string
    {
        return self::meta($event)[0];
    }

    public static function tone(string $event): string
    {
        return self::meta($event)[1];
    }

    /** @return array{0: string, 1: string} */
    private static function meta(string $event): array
    {
        if (isset(self::EVENTS[$event])) {
            return self::EVENTS[$event];
        }
        if (str_ends_with($event, '.deleted') || str_ends_with($event, '_deleted')) {
            return [self::GROUPS[strtok($event, '.')][0] ?? 'trash', 'danger'];
        }

        return self::GROUPS[strtok($event, '.')] ?? ['activity', 'secondary'];
    }
}
