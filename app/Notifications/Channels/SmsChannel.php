<?php

namespace App\Notifications\Channels;

use App\Services\Messaging\SmsService;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsService $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toText') || empty($notifiable->phone)) {
            return;
        }
        $this->sms->send($notifiable->phone, $notification->toText($notifiable), $notifiable->tenant_id ?? null, class_basename($notification));
    }
}
