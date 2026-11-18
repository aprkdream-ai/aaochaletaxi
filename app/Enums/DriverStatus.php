<?php

namespace App\Enums;

enum DriverStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::OnLeave => __('On leave'),
            self::Inactive => __('Inactive'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OnLeave => 'warning',
            self::Inactive => 'secondary',
        };
    }
}
