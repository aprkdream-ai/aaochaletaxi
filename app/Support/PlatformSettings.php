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
                'online_collection_fee_percent' => ['label' => __('Fee on online collections settled to operators (%)'), 'type' => 'number', 'step' => '0.01', 'rules' => ['required', 'numeric', 'min:0', 'max:10']],
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
            'payments' => ['label' => __('Payments (Razorpay)'), 'icon' => 'credit-card', 'test' => 'razorpay', 'fields' => [
                'razorpay_mode' => ['label' => __('Mode'), 'type' => 'select', 'options' => ['disabled' => __('Disabled'), 'test' => __('Test (no real money)'), 'live' => __('Live')], 'rules' => ['required', 'in:disabled,test,live'], 'default' => 'disabled'],
                'razorpay_key_id' => ['label' => 'Key ID', 'type' => 'text', 'rules' => ['nullable', 'required_unless:razorpay_mode,disabled', 'regex:/^rzp_(test|live)_[A-Za-z0-9]+$/'], 'help' => 'rzp_test_… / rzp_live_…'],
                'razorpay_key_secret' => ['label' => 'Key secret', 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:200']],
                'razorpay_webhook_secret' => ['label' => __('Webhook secret'), 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:200'], 'help' => __('Webhook URL: :url — events: payment.captured, order.paid', ['url' => url('/webhooks/razorpay')])],
            ]],
            'email' => ['label' => __('Email'), 'icon' => 'envelope', 'test' => 'mail', 'fields' => [
                'mail_mailer' => ['label' => __('Delivery method'), 'type' => 'select', 'options' => ['' => __('Server default (.env)'), 'sendmail' => 'Sendmail (Hostinger)', 'smtp' => 'SMTP', 'log' => __('Log only (testing)')], 'rules' => ['nullable', 'in:sendmail,smtp,log']],
                'mail_host' => ['label' => __('SMTP host'), 'type' => 'text', 'rules' => ['nullable', 'required_if:mail_mailer,smtp', 'string', 'max:150'], 'help' => 'smtp.hostinger.com'],
                'mail_port' => ['label' => __('SMTP port'), 'type' => 'number', 'rules' => ['nullable', 'required_if:mail_mailer,smtp', 'integer', 'between:1,65535']],
                'mail_encryption' => ['label' => __('Encryption'), 'type' => 'select', 'options' => ['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => __('None')], 'rules' => ['nullable', 'in:ssl,tls,none'], 'default' => 'ssl'],
                'mail_username' => ['label' => __('SMTP username'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:150']],
                'mail_password' => ['label' => __('SMTP password'), 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:200']],
                'mail_from_address' => ['label' => __('From address'), 'type' => 'text', 'rules' => ['nullable', 'email', 'max:150']],
                'mail_from_name' => ['label' => __('From name'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100']],
            ]],
            'sms' => ['label' => __('SMS'), 'icon' => 'chat-left-text', 'test' => 'sms', 'fields' => [
                'sms_provider' => ['label' => __('Provider'), 'type' => 'select', 'options' => ['none' => __('Disabled'), 'msg91' => 'MSG91 (Flow API)', 'fast2sms' => 'Fast2SMS', 'log' => __('Log only (testing)')], 'rules' => ['required', 'in:none,msg91,fast2sms,log'], 'default' => 'none'],
                'sms_api_key' => ['label' => __('API key / auth key'), 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:500']],
                'sms_sender_id' => ['label' => __('Sender ID (DLT header)'), 'type' => 'text', 'rules' => ['nullable', 'alpha_num:ascii', 'max:11']],
                'sms_template_id' => ['label' => __('Template ID (MSG91 flow / DLT template)'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100'], 'help' => __('Template with one variable for the message text, e.g. ##var1## (MSG91) or {#var#} (DLT).')],
            ]],
            'whatsapp' => ['label' => __('WhatsApp'), 'icon' => 'whatsapp', 'test' => 'whatsapp', 'fields' => [
                'whatsapp_enabled' => ['label' => __('Enable WhatsApp messages'), 'type' => 'toggle', 'rules' => ['nullable', 'boolean']],
                'whatsapp_phone_number_id' => ['label' => __('Phone number ID'), 'type' => 'text', 'rules' => ['nullable', 'required_if:whatsapp_enabled,1', 'digits_between:5,30']],
                'whatsapp_access_token' => ['label' => __('Permanent access token'), 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:1000']],
                'whatsapp_api_version' => ['label' => __('Graph API version'), 'type' => 'text', 'rules' => ['nullable', 'regex:/^v\d{2}\.\d$/'], 'default' => 'v21.0'],
                'whatsapp_template' => ['label' => __('Notification template name'), 'type' => 'text', 'rules' => ['nullable', 'alpha_dash:ascii', 'max:100'], 'help' => __('Approved utility template with one body variable {{1}} for the message text.')],
                'whatsapp_template_language' => ['label' => __('Template language'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:10'], 'default' => 'en'],
            ]],
            'maps' => ['label' => __('Google Maps'), 'icon' => 'geo-alt', 'test' => 'maps', 'fields' => [
                'google_maps_browser_key' => ['label' => __('Browser key (Places autocomplete)'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:200'], 'help' => __('Restrict this key to your domain in Google Cloud console.')],
                'google_maps_api_key' => ['label' => __('Server key (Distance Matrix)'), 'type' => 'secret', 'rules' => ['nullable', 'string', 'max:200']],
            ]],
            'push' => ['label' => __('Push (Firebase)'), 'icon' => 'bell', 'test' => 'push', 'fields' => [
                'fcm_project_id' => ['label' => __('Firebase project ID'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:100']],
                'fcm_web_config' => ['label' => __('Web app config (JSON: apiKey, projectId, messagingSenderId, appId)'), 'type' => 'textarea', 'rules' => ['nullable', 'json', 'max:2000'], 'help' => __('Firebase console → Project settings → Your apps → Web app. Public values, used by the driver app.')],
                'fcm_vapid_key' => ['label' => __('Web push certificate (VAPID public key)'), 'type' => 'text', 'rules' => ['nullable', 'string', 'max:200']],
                'fcm_service_account' => ['label' => __('Service account JSON'), 'type' => 'secret_textarea', 'rules' => ['nullable', 'json', 'max:10000'], 'help' => __('Firebase console → Project settings → Service accounts → Generate new private key.')],
            ]],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function fields(?string $group = null): array
    {
        $groups = self::groups();

        return $group ? $groups[$group]['fields'] : array_merge(...array_column($groups, 'fields'));
    }

    public static function isSecret(array $field): bool
    {
        return in_array($field['type'], ['secret', 'secret_textarea'], true);
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
