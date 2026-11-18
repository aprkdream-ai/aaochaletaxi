<?php

namespace App\Services\Messaging;

use App\Models\MessageLog;
use App\Models\PlatformSetting;
use App\Support\Phone;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp Business Cloud API. Business-initiated messages must use an approved template,
 * so notifications are sent through one generic template whose body variable {{1}} carries the text.
 */
class WhatsAppService
{
    public function isEnabled(): bool
    {
        return (bool) PlatformSetting::getValue('whatsapp_enabled')
            && filled(PlatformSetting::getValue('whatsapp_phone_number_id'))
            && filled(PlatformSetting::getValue('whatsapp_access_token'));
    }

    public function canNotify(): bool
    {
        return $this->isEnabled() && filled(PlatformSetting::getValue('whatsapp_template'));
    }

    /** Send the configured notification template with the text as {{1}}. */
    public function notify(string $phone, string $text, ?int $tenantId = null, ?string $context = null): MessageLog
    {
        return $this->sendTemplate($phone, (string) PlatformSetting::getValue('whatsapp_template'), [$text], $tenantId, $context);
    }

    /** @param list<string> $params */
    public function sendTemplate(string $phone, string $template, array $params = [], ?int $tenantId = null, ?string $context = null): MessageLog
    {
        $components = $params ? [['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => mb_substr($p, 0, 1000)], $params)]] : [];

        return $this->dispatch($phone, [
            'type' => 'template',
            'template' => array_filter(['name' => $template, 'language' => ['code' => PlatformSetting::getValue('whatsapp_template_language', 'en')], 'components' => $components]),
        ], "[template:$template] ".implode(' | ', $params), $tenantId, $context);
    }

    private function dispatch(string $phone, array $message, string $logBody, ?int $tenantId, ?string $context): MessageLog
    {
        $to = Phone::normalize($phone);
        $base = ['tenant_id' => $tenantId, 'channel' => 'whatsapp', 'provider' => 'meta', 'recipient' => (string) $to, 'body' => $logBody, 'context' => $context];

        if (! $this->isEnabled()) {
            return MessageLog::record($base + ['status' => 'skipped', 'error' => 'WhatsApp not configured']);
        }
        if (! Phone::isValid($to)) {
            return MessageLog::record($base + ['status' => 'failed', 'error' => 'Invalid Indian mobile number']);
        }

        try {
            $version = PlatformSetting::getValue('whatsapp_api_version') ?: 'v21.0';
            $response = Http::withToken((string) PlatformSetting::getValue('whatsapp_access_token'))->acceptJson()->timeout(20)
                ->post("https://graph.facebook.com/{$version}/".PlatformSetting::getValue('whatsapp_phone_number_id').'/messages',
                    ['messaging_product' => 'whatsapp', 'to' => '91'.$to] + $message);

            $id = $response->json('messages.0.id');

            return MessageLog::record($base + ($response->successful() && $id
                ? ['status' => 'sent', 'provider_message_id' => $id]
                : ['status' => 'failed', 'error' => $response->json('error.message') ?? 'HTTP '.$response->status()]));
        } catch (Throwable $e) {
            return MessageLog::record($base + ['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
