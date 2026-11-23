<?php

namespace App\Enums;

use App\Support\Modules;

/**
 * What an operator runs. Decides which product modules are switched on (see App\Support\Modules).
 * Chosen at registration; afterwards only the super admin can change it (pricing depends on it).
 */
enum BusinessType: string
{
    case Taxi = 'taxi';
    case Fleet = 'fleet';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Taxi => __('Taxi & travels'),
            self::Fleet => __('Fleet management'),
            self::Both => __('Taxi + Fleet'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Taxi => __('Customer bookings, fares, quotations, GST trip invoices and booking page'),
            self::Fleet => __('Company or institution vehicles: usage log, fuel, service and costs'),
            self::Both => __('Everything in Taxi and Fleet (Fleet is billed as an add-on)'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Taxi => 'taxi-front',
            self::Fleet => 'truck-front',
            self::Both => 'layers',
        };
    }

    /** @return list<string> */
    public function modules(): array
    {
        return match ($this) {
            self::Taxi => [Modules::TAXI],
            self::Fleet => [Modules::FLEET],
            self::Both => [Modules::TAXI, Modules::FLEET],
        };
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules(), true);
    }

    /** The second module on top of the plan is charged at the plan's add-on price. */
    public function hasAddon(): bool
    {
        return $this === self::Both;
    }
}
