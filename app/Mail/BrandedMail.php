<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One branded transactional email (OTP, customer booking updates, test mail) in the AaoChaleTaxi theme.
 * Plain data only, so it can be queued without tenant context.
 */
class BrandedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $lines  paragraphs (plain text, escaped)
     * @param  array<string, string>  $details  label => value rows shown in a highlighted panel
     */
    public function __construct(
        public string $subjectLine,
        public string $heading,
        public array $lines = [],
        public array $details = [],
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public ?string $code = null,
        public ?string $note = null,
        public ?string $signOff = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.branded');
    }
}
