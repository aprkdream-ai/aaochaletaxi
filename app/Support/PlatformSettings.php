<?php

namespace App\Support;

use App\Models\PlatformSetting;

/** Definitions for admin-editable platform settings (validation, grouping, secrecy). */
class PlatformSettings
{
    /** @return array<string, array{label:string, fields: array<string, array<string, mixed>>}> */
    public static function groups(): array
    {
        return [
            'billing' => ['label' => __('Billing & trials'), 'icon' => 'credit-card', 'fields' => [
                'trial_plan_code' => ['label' => __('Trial plan'), 'type' => 'plan', 'rules' => ['required', 'exists:subscription_plans,code']],
                'trial_days' => ['label' => __('Trial length (days)'), 'type' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:90']],
                'grace_days' => ['label' => __('Grace period after expiry (days)'), 'type' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:30']],
                'subscription_gst_percent' => ['label' => __('GST on subscriptions (%)'), 'type' => 'number', 'step' => '0.01', 'rules' => ['required', 'numeric', 'min:0', 'max:28']],
                'default_gst_percent' => ['label' => __('Default GST on taxi fares (%)'), 'type' => 'number', 'step' => '0.01', 'rules' => ['required', 'numeric', 'min:0', 'max:28']],
                'marketplace_commission_percent' => ['label' => __('Marketplace commission (%)'), 'type' => 'number', 'step' => '0.01', 'rules' => ['required', 'numeric', 'min:0', 'max:50']],
            ]],
            'company' => ['label' => __('Company (invoice details)'), 'icon' => 'building', 'fields' => [
                'platform_legal_name' => ['label' => __('Legal name'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:200']],
                'platform_gstin' => ['label' => __('GSTIN'), 'type' => 'text', 'rules' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/']],
                'platform_pan' => ['label' => __('PAN'), 'type' => 'text', 'rules' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/']],
                'platform_address' => ['label' => __('Registered address'), 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500']],
                'platform_state' => ['label' => __('State'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100']],
                'platform_bank_details' => ['label' => __('Bank / UPI details shown on invoices'), 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500']],
            ]],
            'integrations' => ['label' => __('Integrations'), 'icon' => 'plug', 'fields' => [
                'razorpay_key_id' => ['label' => 'Razorpay key ID', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100']],
                'razorpay_key_secret' => ['label' => 'Razorpay key secret', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:200']],
                'razorpay_webhook_secret' => ['label' => 'Razorpay webhook secret', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:200']],
                'whatsapp_phone_number_id' => ['label' => 'WhatsApp phone number ID', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100']],
                'whatsapp_access_token' => ['label' => 'WhatsApp access token', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:1000']],
                'sms_sender_id' => ['label' => __('SMS sender ID'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:20']],
                'sms_api_key' => ['label' => __('SMS API key'), 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:500']],
                'google_maps_api_key' => ['label' => 'Google Maps API key', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:200']],
                'fcm_server_key' => ['label' => 'Firebase (FCM) server key', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:1000']],
            ]],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function fields(?string $group = null): array
    {
        $groups = self::groups();

        return $group ? $groups[$group]['fields'] : array_merge(...array_column($groups, 'fields'));
    }

    public static function decimal(string $key, string $default): string
    {
        return number_format((float) PlatformSetting::getValue($key, $default), 2, '.', '');
    }

    public static function int(string $key, int $default): int
    {
        return (int) PlatformSetting::getValue($key, $default);
    }
}
