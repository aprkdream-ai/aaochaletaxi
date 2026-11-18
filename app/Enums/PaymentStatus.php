<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case AdvancePaid = 'advance_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::AdvancePaid => __('Advance paid'),
            self::Paid => __('Fully paid'),
            self::Refunded => __('Refunded'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Unpaid => 'secondary',
            self::AdvancePaid => 'warning',
            self::Paid => 'success',
            self::Refunded => 'danger',
        };
    }
}
