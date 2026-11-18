<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Enquiry = 'enquiry';
    case Quoted = 'quoted';
    case Confirmed = 'confirmed';
    case Assigned = 'assigned';
    case Started = 'started';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => __('Enquiry'),
            self::Quoted => __('Quoted'),
            self::Confirmed => __('Confirmed'),
            self::Assigned => __('Assigned'),
            self::Started => __('On trip'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Enquiry => 'secondary',
            self::Quoted => 'info',
            self::Confirmed => 'warning',
            self::Assigned => 'info',
            self::Started => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Allowed next states. */
    public function next(): array
    {
        return match ($this) {
            self::Enquiry => [self::Quoted, self::Cancelled],
            self::Quoted => [self::Quoted, self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Quoted, self::Assigned, self::Cancelled],
            self::Assigned => [self::Assigned, self::Confirmed, self::Started, self::Cancelled],
            self::Started => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
