<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Account = 'account';
    case Kyc = 'kyc';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Billing => __('Billing & subscription'),
            self::Technical => __('Technical issue'),
            self::Account => __('Account & access'),
            self::Kyc => __('KYC & verification'),
            self::Other => __('Other'),
        };
    }
}
