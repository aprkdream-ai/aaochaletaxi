<?php

namespace App\Support;

/** Single source of truth for permission keys and default role → permission mapping. */
class PermissionCatalog
{
    /** @return array<string, array{group:string,label:string,scope:string}> */
    public static function all(): array
    {
        $tenant = [
            'dashboard.view' => ['Dashboard', 'View dashboard'],
            'branches.manage' => ['Branches', 'Manage branches'],
            'staff.manage' => ['Staff', 'Manage staff users'],
            'roles.manage' => ['Staff', 'Manage roles & permissions'],
            'vehicles.view' => ['Vehicles', 'View vehicles'],
            'vehicles.manage' => ['Vehicles', 'Manage vehicles'],
            'drivers.view' => ['Drivers', 'View drivers'],
            'drivers.manage' => ['Drivers', 'Manage drivers'],
            'bookings.view' => ['Bookings', 'View bookings'],
            'bookings.create' => ['Bookings', 'Create bookings'],
            'bookings.manage' => ['Bookings', 'Edit / cancel bookings'],
            'bookings.assign' => ['Bookings', 'Assign vehicles & drivers'],
            'fares.manage' => ['Pricing', 'Manage fare rules'],
            'fleet_trips.view' => ['Fleet trips', 'View fleet trips'],
            'fleet_trips.manage' => ['Fleet trips', 'Create, assign & close fleet trips'],
            'departments.manage' => ['Fleet trips', 'Manage departments / cost centres'],
            'customers.view' => ['Customers', 'View customers'],
            'invoices.view' => ['Finance', 'View invoices'],
            'invoices.manage' => ['Finance', 'Create / issue invoices'],
            'payments.manage' => ['Finance', 'Record payments & refunds'],
            'expenses.manage' => ['Finance', 'Manage expenses'],
            'settlements.manage' => ['Finance', 'Driver & vendor settlements'],
            'reports.view' => ['Reports', 'View reports'],
            'settings.manage' => ['Settings', 'Business settings & branding'],
            'subscription.manage' => ['Settings', 'Manage subscription'],
        ];

        $platform = [
            'admin.dashboard.view' => ['Admin', 'View admin dashboard'],
            'admin.tenants.view' => ['Admin: Operators', 'View operators'],
            'admin.tenants.manage' => ['Admin: Operators', 'Approve / suspend operators'],
            'admin.plans.manage' => ['Admin: Billing', 'Manage subscription plans'],
            'admin.subscriptions.manage' => ['Admin: Billing', 'Manage subscriptions'],
            'admin.settings.manage' => ['Admin: Settings', 'Platform settings'],
            'admin.audit.view' => ['Admin: Settings', 'View audit logs'],
            'admin.users.manage' => ['Admin: Users', 'Manage platform users'],
            'admin.support.manage' => ['Admin: Support', 'Handle support tickets & enquiries'],
            'admin.reports.view' => ['Admin: Reports', 'Platform reports'],
        ];

        $out = [];
        foreach ($tenant as $key => [$group, $label]) {
            $out[$key] = ['group' => $group, 'label' => $label, 'scope' => 'tenant'];
        }
        foreach ($platform as $key => [$group, $label]) {
            $out[$key] = ['group' => $group, 'label' => $label, 'scope' => 'platform'];
        }

        return $out;
    }

    /** @return list<string> */
    public static function tenantKeys(): array
    {
        return array_keys(array_filter(self::all(), fn ($p) => $p['scope'] === 'tenant'));
    }

    /** Default roles created for every new tenant. @return array<string, array{name:string, description:string, permissions:list<string>}> */
    public static function defaultTenantRoles(): array
    {
        $all = self::tenantKeys();

        return [
            'owner' => [
                'name' => 'Owner',
                'description' => 'Full access to the business account',
                'permissions' => $all,
            ],
            'branch-manager' => [
                'name' => 'Branch Manager',
                'description' => 'Runs day-to-day branch operations',
                'permissions' => array_values(array_diff($all, ['roles.manage', 'settings.manage', 'subscription.manage'])),
            ],
            'booking-manager' => [
                'name' => 'Booking Manager',
                'description' => 'Handles bookings and dispatch',
                'permissions' => ['dashboard.view', 'bookings.view', 'bookings.create', 'bookings.manage', 'bookings.assign', 'customers.view', 'vehicles.view', 'drivers.view', 'fleet_trips.view', 'fleet_trips.manage'],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Finance, invoices and settlements',
                'permissions' => ['dashboard.view', 'bookings.view', 'customers.view', 'invoices.view', 'invoices.manage', 'payments.manage', 'expenses.manage', 'settlements.manage', 'reports.view'],
            ],
        ];
    }

    /** Platform roles (tenant_id NULL) for platform staff. */
    public static function defaultPlatformRoles(): array
    {
        return [
            'platform-support' => [
                'name' => 'Platform Support',
                'description' => 'Operator support and enquiries',
                'permissions' => ['admin.dashboard.view', 'admin.tenants.view', 'admin.support.manage', 'admin.audit.view'],
            ],
            'platform-finance' => [
                'name' => 'Platform Finance',
                'description' => 'Plans, subscriptions and revenue',
                'permissions' => ['admin.dashboard.view', 'admin.tenants.view', 'admin.plans.manage', 'admin.subscriptions.manage', 'admin.reports.view'],
            ],
        ];
    }
}
