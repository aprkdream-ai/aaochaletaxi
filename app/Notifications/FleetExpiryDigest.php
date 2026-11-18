<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FleetExpiryDigest extends Notification implements ShouldQueue
{
    use Queueable, UsesConfiguredChannels;

    /** @param list<array{kind:string, name:string, what:string, date:string, days:int, url:string}> $items */
    public function __construct(public array $items) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(__('Fleet documents expiring soon (:n)', ['n' => count($this->items)]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('These documents need your attention:'));
        foreach (array_slice($this->items, 0, 25) as $i) {
            $mail->line('• '.$i['name'].' — '.$i['what'].': '.($i['days'] < 0 ? __('expired on :d', ['d' => $i['date']]) : __('expires :d (:n days)', ['d' => $i['date'], 'n' => $i['days']])));
        }

        return $mail->action(__('Open fleet'), route('operator.vehicles.index', ['compliance' => 'attention']));
    }

    public function toText(object $notifiable): string
    {
        $first = $this->items[0];

        return __('AaoChaleTaxi: :n fleet document(s) expiring. First: :name :what on :date.', ['n' => count($this->items), 'name' => $first['name'], 'what' => $first['what'], 'date' => $first['date']]);
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'fleet_expiry', 'count' => count($this->items), 'items' => array_slice($this->items, 0, 20)];
    }
}
