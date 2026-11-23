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
        $mail = (new MailMessage)->subject(__('Fleet reminders: documents & service (:n)', ['n' => count($this->items)]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('These need your attention:'));
        foreach (array_slice($this->items, 0, 25) as $i) {
            $mail->line('• '.$i['name'].' — '.$i['what'].': '.self::when($i));
        }

        return $mail->action(__('Open fleet'), route('operator.vehicles.index', ['compliance' => 'attention']));
    }

    public static function when(array $i): string
    {
        if (($i['kind'] ?? null) === 'service') {
            $parts = [];
            if (isset($i['days'])) {
                $parts[] = $i['days'] < 0 ? __('overdue since :d', ['d' => $i['date']]) : trans_choice('{0} today|{1} tomorrow|[2,*] in :n days', $i['days'], ['n' => $i['days']]);
            }
            if (isset($i['km_left'])) {
                $parts[] = $i['km_left'] <= 0 ? __(':n km overdue', ['n' => number_format(abs($i['km_left']))]) : __(':n km left', ['n' => number_format($i['km_left'])]);
            }

            return $i['date'].' ('.implode(', ', $parts).')';
        }

        if (($i['kind'] ?? null) === 'emi') {
            return $i['days'] < 0 ? __('overdue since :d', ['d' => $i['date']]) : __('due :d (:n days)', ['d' => $i['date'], 'n' => $i['days']]);
        }

        return $i['days'] < 0 ? __('expired on :d', ['d' => $i['date']]) : __('expires :d (:n days)', ['d' => $i['date'], 'n' => $i['days']]);
    }

    public function toText(object $notifiable): string
    {
        $first = $this->items[0];

        return __('AaoChaleTaxi: :n fleet reminder(s). First: :name :what — :when.', ['n' => count($this->items), 'name' => $first['name'], 'what' => $first['what'], 'when' => self::when($first)]);
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'fleet_expiry', 'count' => count($this->items), 'items' => array_slice($this->items, 0, 20)];
    }
}
