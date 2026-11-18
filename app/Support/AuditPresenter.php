<?php

namespace App\Support;

/** Human-friendly labels and icons for audit events (dashboards / activity feeds). */
class AuditPresenter
{
    private const ICONS = [
        'auth' => 'box-arrow-in-right', 'tenant' => 'building', 'subscription' => 'credit-card-2-front', 'payment' => 'cash-coin',
        'kyc' => 'patch-check', 'support' => 'life-preserver', 'plan' => 'tags', 'platform' => 'gear', 'staff' => 'person-gear', 'enquiry' => 'inbox',
    ];

    public static function label(string $event): string
    {
        return __(ucfirst(str_replace(['.', '_'], ' ', $event)));
    }

    public static function icon(string $event): string
    {
        return self::ICONS[strtok($event, '.')] ?? 'activity';
    }
}
