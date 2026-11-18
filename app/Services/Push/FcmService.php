<?php

namespace App\Services\Push;

use App\Models\MessageLog;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Firebase Cloud Messaging HTTP v1 with a service-account JWT (signed with openssl; no SDK). */
class FcmService
{
    public function isEnabled(): bool
    {
        return filled(PlatformSetting::getValue('fcm_project_id')) && filled(PlatformSetting::getValue('fcm_service_account'));
    }

    public function send(string $token, string $title, string $body, array $data = [], ?int $tenantId = null, ?string $context = null): MessageLog
    {
        $base = ['tenant_id' => $tenantId, 'channel' => 'push', 'provider' => 'fcm', 'recipient' => mb_substr($token, 0, 250), 'subject' => $title, 'body' => $body, 'context' => $context];

        if (! $this->isEnabled()) {
            return MessageLog::record($base + ['status' => 'skipped', 'error' => 'FCM not configured']);
        }

        try {
            $response = Http::withToken($this->accessToken())->acceptJson()->timeout(15)
                ->post('https://fcm.googleapis.com/v1/projects/'.PlatformSetting::getValue('fcm_project_id').'/messages:send', [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => array_map('strval', $data),
                        'webpush' => ['fcm_options' => ['link' => $data['link'] ?? url('/')]],
                    ],
                ]);

            return MessageLog::record($base + ($response->successful()
                ? ['status' => 'sent', 'provider_message_id' => $response->json('name')]
                : ['status' => 'failed', 'error' => $response->json('error.message') ?? 'HTTP '.$response->status()]));
        } catch (Throwable $e) {
            return MessageLog::record($base + ['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    /** OAuth2 access token from the service account (cached ~55 min). */
    public function accessToken(): string
    {
        $sa = json_decode((string) PlatformSetting::getValue('fcm_service_account'), true);
        if (! is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            throw new RuntimeException('Invalid service account JSON.');
        }

        return Cache::remember('fcm:token:'.md5($sa['client_email']), now()->addMinutes(55), function () use ($sa) {
            $now = time();
            $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]));
            if (! openssl_sign($unsigned, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign JWT with the service account key.');
            }

            $r = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$b64($signature),
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new RuntimeException('Google OAuth failed: '.($r->json('error_description') ?? 'HTTP '.$r->status()));
            }

            return $r->json('access_token');
        });
    }

    /** @return array{ok:bool, message:string} */
    public function test(?string $token): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => __('Project ID and service account JSON are required.')];
        }
        try {
            $this->accessToken();
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        if (! $token) {
            return ['ok' => true, 'message' => __('Authenticated with Google. Add a device token to send a test notification.')];
        }
        $log = $this->send($token, 'AaoChaleTaxi', __('Test notification'), [], null, 'admin.test');

        return ['ok' => $log->status === 'sent', 'message' => $log->status === 'sent' ? __('Test notification sent.') : $log->error];
    }
}
