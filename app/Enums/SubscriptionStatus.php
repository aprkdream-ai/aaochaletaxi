<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function isUsable(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::Grace], true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Trialing => 'info',
            self::Active => 'success',
            self::Grace, self::PastDue => 'warning',
            default => 'secondary',
        };
    }
}
