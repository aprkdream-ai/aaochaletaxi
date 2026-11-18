<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Messaging\SmsService;
use App\Services\Messaging\WhatsAppService;

/** Database always; mail when the user has an email; SMS / WhatsApp when configured by the super admin. */
trait UsesConfiguredChannels
{
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email)) {
            $channels[] = 'mail';
        }
        if (! empty($notifiable->phone)) {
            if (app(SmsService::class)->isEnabled()) {
                $channels[] = SmsChannel::class;
            }
            if (app(WhatsAppService::class)->canNotify()) {
                $channels[] = WhatsAppChannel::class;
            }
        }

        return $channels;
    }
}
