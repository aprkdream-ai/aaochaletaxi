<?php

namespace App\Providers;

use App\Models\MessageLog;
use App\Models\User;
use App\Services\Messaging\MailConfigurator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        MailConfigurator::apply();

        // Delivery log for e-mail (SMS/WhatsApp/push log themselves).
        Event::listen(MessageSent::class, function ($event) {
            try {
                MessageLog::record([
                    'channel' => 'mail', 'provider' => config('mail.default'),
                    'recipient' => implode(', ', array_map(fn ($a) => $a->getAddress(), $event->message->getTo())),
                    'subject' => $event->message->getSubject(), 'status' => 'sent',
                    'provider_message_id' => $event->sent->getMessageId(),
                ]);
            } catch (\Throwable) {
                // never break mail delivery because of logging
            }
        });
        Event::listen(NotificationFailed::class, function ($event) {
            try {
                MessageLog::record([
                    'channel' => is_string($event->channel) ? class_basename($event->channel) : 'unknown', 'recipient' => (string) ($event->notifiable->email ?? $event->notifiable->phone ?? '?'),
                    'status' => 'failed', 'error' => json_encode($event->data), 'context' => class_basename($event->notification),
                ]);
            } catch (\Throwable) {
            }
        });

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Paginator::useBootstrapFive();

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Super admin passes every gate; dotted abilities map to role permissions.
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            if (str_contains($ability, '.') && $user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('login')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('forms', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Named limiters: plain `throttle:x,y` shares ONE counter per IP/user across all routes, so each
        // action gets its own key here (e.g. fare previews must never block the OTP request).
        $byIp = fn (string $name, int $perMinute) => RateLimiter::for($name, fn (Request $r) => Limit::perMinute($perMinute)->by($name.'|'.$r->ip()));
        $byUser = fn (string $name, int $perMinute) => RateLimiter::for($name, fn (Request $r) => Limit::perMinute($perMinute)->by($name.'|'.($r->user()?->id ?? $r->ip())));
        $byIp('otp-send', 6);
        $byIp('otp-verify', 12);
        $byIp('fare-preview', 40);
        $byIp('suggest', 90);
        $byIp('track-actions', 10);
        $byIp('webhooks', 300);
        $byUser('driver-check', 10);
        $byUser('operator-preview', 120);
        $byUser('operator-distance', 40);
        $byUser('driver-poll', 10);
        $byUser('driver-location', 6);
        RateLimiter::for('public-booking', fn (Request $r) => Limit::perHour(15)->by('public-booking|'.$r->ip()));
    }
}
