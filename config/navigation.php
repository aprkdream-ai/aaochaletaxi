<?php

// Sidebar navigation per panel. Items are shown only if the user has `permission` (or none set).
return [
    'admin' => [
        ['section' => 'Overview'],
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'admin.dashboard', 'permission' => 'admin.dashboard.view'],
        ['section' => 'Operators'],
        ['label' => 'All operators', 'icon' => 'buildings', 'route' => 'admin.tenants.index', 'active' => 'admin.tenants.*', 'permission' => 'admin.tenants.view'],
        ['label' => 'Pending approvals', 'icon' => 'hourglass-split', 'route' => 'admin.tenants.index', 'params' => ['status' => 'pending'], 'permission' => 'admin.tenants.view', 'active' => '__none__'],
    ],
    'operator' => [
        ['section' => 'Overview'],
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'operator.dashboard', 'permission' => 'dashboard.view'],
    ],
];
