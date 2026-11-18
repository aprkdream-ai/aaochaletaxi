<?php

return [
    'version' => '0.2.0',
    'support_email' => env('SUPPORT_EMAIL', 'support@aaochaletaxi.com'),
    'support_phone' => env('SUPPORT_PHONE'),
    // E.164 for tel:/wa.me links, derived from SUPPORT_PHONE (last 10 digits).
    'support_phone_e164' => ($p = preg_replace('/\D/', '', (string) env('SUPPORT_PHONE'))) && strlen($p) >= 10 ? '+91'.substr($p, -10) : null,
    // Run queue + hourly jobs after web responses when no cron heartbeat is seen (shared hosting fallback).
    'web_scheduler' => (bool) env('WEB_SCHEDULER_FALLBACK', true),
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
