<?php

namespace App\Enums;

enum UserType: string
{
    case SuperAdmin = 'super_admin';
    case PlatformStaff = 'platform_staff';
    case Operator = 'operator';
    case Driver = 'driver';
    case Customer = 'customer';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::PlatformStaff => 'Platform Staff',
            self::Operator => 'Operator Staff',
            self::Driver => 'Driver',
            self::Customer => 'Customer',
            self::Vendor => 'Vendor / Partner',
        };
    }

    public function isPlatform(): bool
    {
        return in_array($this, [self::SuperAdmin, self::PlatformStaff], true);
    }

    public function belongsToTenant(): bool
    {
        return in_array($this, [self::Operator, self::Driver, self::Vendor], true);
    }

    /** Route name of this user type's home screen. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::SuperAdmin, self::PlatformStaff => 'admin.dashboard',
            self::Operator, self::Vendor => 'operator.dashboard',
            self::Driver => 'driver.home',
            self::Customer => 'account.dashboard',
        };
    }
}
