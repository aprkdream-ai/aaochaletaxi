<?php

return [
    'support_email' => env('SUPPORT_EMAIL', 'support@aaochaletaxi.com'),
    'support_phone' => env('SUPPORT_PHONE'),
    'legal_name' => env('COMPANY_LEGAL_NAME', 'AaoChaleTaxi'),
    'grievance_email' => env('GRIEVANCE_EMAIL', env('SUPPORT_EMAIL', 'support@aaochaletaxi.com')),

    // Used only by the seeder to create the first super admin when none exists.
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'phone' => env('SUPER_ADMIN_PHONE'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],
];
