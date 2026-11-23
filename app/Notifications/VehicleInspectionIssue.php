<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Driver's daily checklist reported problems. Plain data (no models) so the queued job needs no tenant context. */
class VehicleInspectionIssue extends Notification implements ShouldQueue
{
    use Queueable, UsesConfiguredChannels;

    /** @param list<string> $issues */
    public function __construct(public string $vehicle, public string $driver, public array $issues, public ?string $notes, public string $url) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->toText($notifiable))->line(__('Reported by :d today:', ['d' => $this->driver]));
        foreach ($this->issues as $issue) {
            $mail->line('• '.$issue);
        }
        if ($this->notes) {
            $mail->line($this->notes);
        }

        return $mail->action(__('Open vehicle'), $this->url);
    }

    public function toText(object $notifiable): string
    {
        return __('Daily check: :n issue(s) on :v — :list', ['n' => count($this->issues), 'v' => $this->vehicle, 'list' => implode(', ', $this->issues)]);
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'inspection_issue', 'vehicle' => $this->vehicle, 'driver' => $this->driver, 'issues' => $this->issues, 'url' => $this->url];
    }
}
