<?php

// Sidebar navigation per panel. Items are shown only if the user has `permission` (or none set).
// `badge` names a key of the $navBadges array computed in the panel layout.
return [
    'admin' => [
        ['section' => 'Overview'],
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'admin.dashboard', 'permission' => 'admin.dashboard.view'],
        ['section' => 'Operators'],
        ['label' => 'All operators', 'icon' => 'buildings', 'route' => 'admin.tenants.index', 'active' => 'admin.tenants.*', 'permission' => 'admin.tenants.view'],
        ['label' => 'Subscriptions', 'icon' => 'credit-card-2-front', 'route' => 'admin.subscriptions.index', 'active' => 'admin.subscriptions.*', 'permission' => 'admin.subscriptions.manage'],
        ['label' => 'Plans', 'icon' => 'tags', 'route' => 'admin.plans.index', 'active' => 'admin.plans.*', 'permission' => 'admin.plans.manage'],
        ['section' => 'Support'],
        ['label' => 'Support tickets', 'icon' => 'life-preserver', 'route' => 'admin.support.index', 'active' => 'admin.support.*', 'permission' => 'admin.support.manage', 'badge' => 'tickets'],
        ['label' => 'Website enquiries', 'icon' => 'inbox', 'route' => 'admin.enquiries.index', 'active' => 'admin.enquiries.*', 'permission' => 'admin.support.manage', 'badge' => 'enquiries'],
        ['section' => 'Platform'],
        ['label' => 'Staff & roles', 'icon' => 'person-gear', 'route' => 'admin.staff.index', 'active' => 'admin.staff.*', 'permission' => 'admin.users.manage'],
        ['label' => 'Settings', 'icon' => 'gear', 'route' => 'admin.settings.edit', 'active' => 'admin.settings.*', 'permission' => 'admin.settings.manage'],
        ['label' => 'Audit log', 'icon' => 'journal-text', 'route' => 'admin.audit.index', 'permission' => 'admin.audit.view'],
    ],
    'operator' => [
        ['section' => 'Overview'],
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'operator.dashboard', 'permission' => 'dashboard.view'],
        ['section' => 'Business'],
        ['label' => 'Business profile', 'icon' => 'building', 'route' => 'operator.profile.edit', 'permission' => 'settings.manage'],
        ['label' => 'KYC documents', 'icon' => 'patch-check', 'route' => 'operator.kyc.index', 'permission' => 'settings.manage'],
        ['label' => 'Subscription', 'icon' => 'credit-card-2-front', 'route' => 'operator.subscription', 'active' => 'operator.subscription', 'permission' => 'subscription.manage'],
        ['section' => 'Help'],
        ['label' => 'Support', 'icon' => 'life-preserver', 'route' => 'operator.support.index', 'active' => 'operator.support.*'],
    ],
];
