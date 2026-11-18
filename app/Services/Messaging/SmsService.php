<?php

namespace App\Services\Messaging;

use App\Models\MessageLog;
use App\Models\PlatformSetting;
use App\Support\Phone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** SMS via MSG91 (Flow API) or Fast2SMS (DLT route). Every attempt is written to message_logs. */
class SmsService
{
    public function provider(): string
    {
        return (string) PlatformSetting::getValue('sms_provider', 'none');
    }

    public function isEnabled(): bool
    {
        return match ($this->provider()) {
            'log' => true,
            'msg91', 'fast2sms' => filled(PlatformSetting::getValue('sms_api_key')),
            default => false,
        };
    }

    public function send(string $phone, string $message, ?int $tenantId = null, ?string $context = null): MessageLog
    {
        $to = Phone::normalize($phone);
        $base = ['tenant_id' => $tenantId, 'channel' => 'sms', 'provider' => $this->provider(), 'recipient' => (string) $to, 'body' => $message, 'context' => $context];

        if (! $this->isEnabled()) {
            return MessageLog::record($base + ['status' => 'skipped', 'error' => 'SMS provider not configured']);
        }
        if (! Phone::isValid($to)) {
            return MessageLog::record($base + ['status' => 'failed', 'error' => 'Invalid Indian mobile number']);
        }

        try {
            [$ok, $id, $error] = match ($this->provider()) {
                'msg91' => $this->msg91($to, $message),
                'fast2sms' => $this->fast2sms($to, $message),
                default => $this->logOnly($to, $message),
            };
        } catch (Throwable $e) {
            [$ok, $id, $error] = [false, null, $e->getMessage()];
        }

        return MessageLog::record($base + ['status' => $ok ? 'sent' : 'failed', 'provider_message_id' => $id, 'error' => $error]);
    }

    /** MSG91 Flow API: template must contain ##var1##. */
    private function msg91(string $to, string $message): array
    {
        $response = Http::withHeaders(['authkey' => PlatformSetting::getValue('sms_api_key')])->acceptJson()->timeout(15)
            ->post('https://control.msg91.com/api/v5/flow/', [
                'template_id' => PlatformSetting::getValue('sms_template_id'),
                'short_url' => '0',
                'recipients' => [['mobiles' => '91'.$to, 'var1' => mb_substr($message, 0, 300)]],
            ]);
        $ok = $response->successful() && $response->json('type') === 'success';

        return [$ok, $response->json('message'), $ok ? null : ($response->json('message') ?? 'HTTP '.$response->status())];
    }

    /** Fast2SMS DLT route: message id = DLT template id, variables_values = text. */
    private function fast2sms(string $to, string $message): array
    {
        $response = Http::withHeaders(['authorization' => PlatformSetting::getValue('sms_api_key')])->acceptJson()->timeout(15)
            ->post('https://www.fast2sms.com/dev/bulkV2', [
                'route' => 'dlt',
                'sender_id' => PlatformSetting::getValue('sms_sender_id'),
                'message' => PlatformSetting::getValue('sms_template_id'),
                'variables_values' => mb_substr($message, 0, 300),
                'numbers' => $to,
                'flash' => 0,
            ]);
        $ok = $response->successful() && $response->json('return') === true;

        return [$ok, $response->json('request_id'), $ok ? null : (is_array($m = $response->json('message')) ? implode(' ', $m) : ($m ?? 'HTTP '.$response->status()))];
    }

    private function logOnly(string $to, string $message): array
    {
        Log::info('[SMS log driver]', ['to' => $to, 'message' => $message]);

        return [true, 'log-'.uniqid(), null];
    }
}
