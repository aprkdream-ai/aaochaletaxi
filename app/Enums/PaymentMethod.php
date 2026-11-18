<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Razorpay = 'razorpay';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::BankTransfer => __('Bank transfer (NEFT/IMPS/RTGS)'),
            self::Cash => __('Cash'),
            self::Cheque => __('Cheque'),
            self::Razorpay => 'Razorpay',
            self::Other => __('Other'),
        };
    }

    /** Methods an admin can record manually. */
    public static function manual(): array
    {
        return [self::Upi, self::BankTransfer, self::Cash, self::Cheque, self::Other];
    }
}
