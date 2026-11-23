<?php

namespace App\Support;

/**
 * Product modules. Core features (vehicles, drivers, documents, maintenance, expenses, team, reports)
 * are always on; module features are enabled by the tenant's business type.
 *
 * Enforcement is through permissions: a permission that belongs to a module the tenant does not have
 * is removed from every user of that tenant (User::permissionKeys), so routes (`permission:`), gates,
 * policies and the sidebar all follow automatically.
 */
class Modules
{
    public const TAXI = 'taxi';

    public const FLEET = 'fleet';

    /** Permission prefix/key → module. Keys not listed are core. */
    private const PERMISSIONS = [
        'bookings.' => self::TAXI,
        'fares.manage' => self::TAXI,
        'customers.view' => self::TAXI,
        'invoices.' => self::TAXI,
        'payments.manage' => self::TAXI,
    ];

    public static function forPermission(string $key): ?string
    {
        foreach (self::PERMISSIONS as $prefix => $module) {
            if ($key === $prefix || (str_ends_with($prefix, '.') && str_starts_with($key, $prefix))) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     * @param  list<string>  $enabled
     * @return list<string>
     */
    public static function filterPermissions(array $keys, array $enabled): array
    {
        return array_values(array_filter($keys, function (string $key) use ($enabled) {
            $module = self::forPermission($key);

            return $module === null || in_array($module, $enabled, true);
        }));
    }
}
