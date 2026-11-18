<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionNotice extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param 'activated'|'expiring'|'grace'|'expired' $kind */
    public function __construct(public string $kind, public Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->subscription;
        $plan = $s->plan->name;
        $mail = (new MailMessage)->greeting(__('Hello :name,', ['name' => $notifiable->name]));

        return match ($this->kind) {
            'activated' => $mail->subject(__(':plan plan activated', ['plan' => $plan]))
                ->line(__('Your :plan plan is active until :date.', ['plan' => $plan, 'date' => $s->ends_at->format('d M Y')]))
                ->action(__('Open dashboard'), route('operator.subscription')),
            'expiring' => $mail->subject(__('Your :plan plan expires on :date', ['plan' => $plan, 'date' => $s->ends_at->format('d M Y')]))
                ->line(__('Your subscription expires in :n day(s). Renew now to avoid interruption.', ['n' => max(0, $s->daysLeft())]))
                ->action(__('Renew subscription'), route('operator.subscription')),
            'grace' => $mail->subject(__('Subscription expired — grace period active'))
                ->line(__('Your subscription ended on :date. You can continue working until :grace. Please renew to avoid losing access.', ['date' => $s->ends_at->format('d M Y'), 'grace' => $s->grace_ends_at?->format('d M Y')]))
                ->action(__('Renew subscription'), route('operator.subscription')),
            default => $mail->subject(__('Subscription expired'))
                ->line(__('Your subscription has expired and access to the dashboard is paused. Your data is safe and will be available as soon as you renew.'))
                ->action(__('Renew subscription'), route('operator.subscription')),
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'subscription_id' => $this->subscription->id,
            'plan' => $this->subscription->plan->name,
            'ends_at' => $this->subscription->ends_at->toIso8601String(),
        ];
    }
}
