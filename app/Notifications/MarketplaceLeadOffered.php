<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** New marketplace quote request offered to the operator (plain data: queued jobs need no tenant context). */
class MarketplaceLeadOffered extends Notification implements ShouldQueue
{
    use Queueable, UsesConfiguredChannels;

    public function __construct(public string $from, public ?string $to, public string $at, public string $type, public string $until) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->toText($notifiable))
            ->line(__('Accept before :t — the first operator to accept gets the booking.', ['t' => $this->until]))
            ->action(__('Open requests'), route('operator.leads.index'));
    }

    public function toText(object $notifiable): string
    {
        return __('New ride request: :type :from:to on :at. Accept before :t.', ['type' => $this->type, 'from' => $this->from, 'to' => $this->to ? ' → '.$this->to : '', 'at' => $this->at, 't' => $this->until]);
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'marketplace_lead', 'from' => $this->from, 'to' => $this->to, 'at' => $this->at, 'until' => $this->until];
    }
}
