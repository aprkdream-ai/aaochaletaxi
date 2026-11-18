<?php

namespace App\Services\Payments;

use App\Models\PlatformSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Thin Razorpay REST client (no SDK — shared-hosting friendly). Credentials come from platform settings. */
class RazorpayGateway
{
    public const API = 'https://api.razorpay.com/v1';

    public function mode(): string
    {
        return (string) PlatformSetting::getValue('razorpay_mode', 'disabled');
    }

    public function isEnabled(): bool
    {
        return in_array($this->mode(), ['test', 'live'], true) && filled($this->keyId()) && filled($this->keySecret());
    }

    public function keyId(): ?string
    {
        return PlatformSetting::getValue('razorpay_key_id');
    }

    private function keySecret(): ?string
    {
        return PlatformSetting::getValue('razorpay_key_secret');
    }

    /** @return array{id:string, amount:int, currency:string, status:string} */
    public function createOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        $response = $this->client()->post('/orders', [
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => mb_substr($receipt, 0, 40),
            'payment_capture' => 1,
            'notes' => array_map(fn ($v) => mb_substr((string) $v, 0, 250), array_slice($notes, 0, 15, true)),
        ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Razorpay order failed: '.($response->json('error.description') ?? 'HTTP '.$response->status()));
        }

        return $response->json();
    }

    /** Checkout handler signature: HMAC_SHA256(order_id|payment_id, key_secret). */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = $this->keySecret();

        return filled($secret) && hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $secret), $signature);
    }

    /** Webhook signature: HMAC_SHA256(raw body, webhook secret). */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = PlatformSetting::getValue('razorpay_webhook_secret');

        return filled($secret) && $signature !== '' && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    /** @return array{ok:bool, message:string} */
    public function test(): array
    {
        if (! filled($this->keyId()) || ! filled($this->keySecret())) {
            return ['ok' => false, 'message' => __('Key ID and secret are required.')];
        }
        if ($this->mode() === 'test' && ! str_starts_with((string) $this->keyId(), 'rzp_test_')) {
            return ['ok' => false, 'message' => __('Test mode needs an rzp_test_ key.')];
        }
        if ($this->mode() === 'live' && ! str_starts_with((string) $this->keyId(), 'rzp_live_')) {
            return ['ok' => false, 'message' => __('Live mode needs an rzp_live_ key.')];
        }

        $response = $this->client()->get('/orders', ['count' => 1]);

        return $response->successful()
            ? ['ok' => true, 'message' => __('Connected to Razorpay (:mode mode).', ['mode' => $this->mode()])]
            : ['ok' => false, 'message' => __('Razorpay rejected the credentials: :e', ['e' => $response->json('error.description') ?? 'HTTP '.$response->status()])];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::API)->withBasicAuth((string) $this->keyId(), (string) $this->keySecret())
            ->acceptJson()->asJson()->timeout(20)->retry(2, 300, throw: false);
    }
}
