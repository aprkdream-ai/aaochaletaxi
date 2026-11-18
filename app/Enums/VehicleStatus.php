<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Maintenance => __('In maintenance'),
            self::Inactive => __('Inactive'),
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Maintenance => 'warning',
            self::Inactive => 'secondary',
        };
    }
}
