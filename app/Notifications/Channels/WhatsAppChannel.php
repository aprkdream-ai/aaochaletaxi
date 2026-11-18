<?php

namespace App\Notifications\Channels;

use App\Services\Messaging\WhatsAppService;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toText') || empty($notifiable->phone)) {
            return;
        }
        $this->whatsapp->notify($notifiable->phone, $notification->toText($notifiable), $notifiable->tenant_id ?? null, class_basename($notification));
    }
}
