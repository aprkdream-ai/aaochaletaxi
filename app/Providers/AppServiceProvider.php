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
    }
}
