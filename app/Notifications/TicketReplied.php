<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReplied extends Notification implements ShouldQueue
{
    use Queueable, UsesConfiguredChannels;

    public function __construct(public SupportTicket $ticket) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('[:number] New reply from AaoChaleTaxi support', ['number' => $this->ticket->number]))
            ->line(__('Our team replied to your ticket “:subject”.', ['subject' => $this->ticket->subject]))
            ->action(__('View reply'), route('operator.support.show', $this->ticket));
    }

    public function toText(object $notifiable): string
    {
        return __('AaoChaleTaxi support replied to ticket :no. View: :url', ['no' => $this->ticket->number, 'url' => route('operator.support.show', $this->ticket)]);
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'ticket_replied', 'ticket' => $this->ticket->number, 'subject' => $this->ticket->subject];
    }
}
