<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantApproved extends Notification implements ShouldQueue
{
    use Queueable, UsesConfiguredChannels;

    public function __construct(public Tenant $tenant) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':name is now live on AaoChaleTaxi', ['name' => $this->tenant->name]))
            ->greeting(__('Congratulations :name!', ['name' => $notifiable->name]))
            ->line(__('Your business account has been verified and activated.'))
            ->action(__('Open your dashboard'), route('operator.dashboard'))
            ->line(__('Need help getting started? Reply to this email or raise a support ticket.'));
    }

    public function toText(object $notifiable): string
    {
        return __('AaoChaleTaxi: :name is verified and live. Login at :url', ['name' => $this->tenant->name, 'url' => route('login')]);
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'tenant_approved', 'tenant_id' => $this->tenant->id];
    }
}
