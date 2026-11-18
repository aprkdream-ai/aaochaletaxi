<?php

namespace App\Notifications\Channels;

use App\Services\Push\FcmService;
use Illuminate\Notifications\Notification;

/** Web push via Firebase to the device token stored on the driver profile. */
class FcmChannel
{
    public function __construct(private FcmService $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $token = method_exists($notification, 'fcmToken') ? $notification->fcmToken($notifiable) : null;
        if (! $token || ! method_exists($notification, 'toPush') || ! $this->fcm->isEnabled()) {
            return;
        }
        $p = $notification->toPush($notifiable);
        $this->fcm->send($token, $p['title'], $p['body'], $p['data'] ?? [], $notifiable->tenant_id ?? null, class_basename($notification));
    }
}
