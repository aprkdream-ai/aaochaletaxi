<?php

namespace App\Services\Messaging;

use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * One-time codes for verifying a mobile number (guest booking, OTP login).
 * Delivery: SMS → WhatsApp → e-mail (if given) → log (non-production only).
 */
class OtpService
{
    public const TTL_MIN = 10;

    public const MAX_ATTEMPTS = 5;

    /** Exposed only while running tests. */
    public static ?string $lastCode = null;

    public function __construct(private SmsService $sms, private WhatsAppService $whatsapp) {}

    public function canDeliver(?string $email = null): bool
    {
        return $this->sms->isEnabled() || $this->whatsapp->canNotify() || filled($email) || ! app()->isProduction();
    }

    /** @return string channel used */
    public function send(string $phone, ?string $email = null, string $purpose = 'verification'): string
    {
        $phone = Phone::normalize($phone);
        if (! Phone::isValid($phone)) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid 10-digit Indian mobile number.')]);
        }
        $key = 'otp-send:'.$phone;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['phone' => __('Too many codes requested. Try again in :m minutes.', ['m' => (int) ceil(RateLimiter::availableIn($key) / 60)])]);
        }
        RateLimiter::hit($key, 15 * 60);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->cacheKey($phone), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::TTL_MIN));
        if (app()->runningUnitTests()) {
            self::$lastCode = $code;
        }

        $text = __(':code is your AaoChaleTaxi verification code. Valid for :m minutes. Do not share it.', ['code' => $code, 'm' => self::TTL_MIN]);

        if ($this->sms->isEnabled() && $this->sms->send($phone, $text, null, 'otp.'.$purpose)->status === 'sent') {
            return 'sms';
        }
        if ($this->whatsapp->canNotify() && $this->whatsapp->notify($phone, $text, null, 'otp.'.$purpose)->status === 'sent') {
            return 'whatsapp';
        }
        if (filled($email)) {
            Mail::raw($text, fn ($m) => $m->to($email)->subject(__('Your AaoChaleTaxi verification code')));

            return 'email';
        }
        if (! app()->isProduction()) {
            Log::info("[OTP] $phone: $code");

            return 'log';
        }

        Cache::forget($this->cacheKey($phone));
        throw ValidationException::withMessages(['phone' => __('We could not send a code right now. Please add your email address or call us to book.')]);
    }

    public function verify(string $phone, string $code): bool
    {
        $phone = Phone::normalize($phone);
        $entry = Cache::get($this->cacheKey($phone));
        if (! $entry) {
            throw ValidationException::withMessages(['otp' => __('The code has expired. Please request a new one.')]);
        }
        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->cacheKey($phone));
            throw ValidationException::withMessages(['otp' => __('Too many wrong attempts. Please request a new code.')]);
        }
        if (! Hash::check($code, $entry['hash'])) {
            $entry['attempts']++;
            Cache::put($this->cacheKey($phone), $entry, now()->addMinutes(self::TTL_MIN));
            throw ValidationException::withMessages(['otp' => __('Incorrect code. :n attempts left.', ['n' => self::MAX_ATTEMPTS - $entry['attempts']])]);
        }
        Cache::forget($this->cacheKey($phone));

        return true;
    }

    private function cacheKey(string $phone): string
    {
        return 'otp:'.hash('sha256', $phone);
    }
}
