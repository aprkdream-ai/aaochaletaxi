<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case AwaitingCustomer = 'awaiting_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::AwaitingCustomer => __('Awaiting your reply'),
            self::Resolved => __('Resolved'),
            self::Closed => __('Closed'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::AwaitingCustomer => 'warning',
            self::Resolved => 'success',
            self::Closed => 'secondary',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Closed;
    }
}
