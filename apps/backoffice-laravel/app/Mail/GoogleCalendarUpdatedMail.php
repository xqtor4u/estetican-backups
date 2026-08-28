<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de que la sincronización a Google Calendar tuvo cambios en las citas que
 * este usuario ve. Se manda desde `calendario:sincronizar-google` una vez por corrida
 * con cambios, solo a los usuarios con `google_calendar_notify_email` activado.
 */
class GoogleCalendarUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{type: string, pet: string, services: string, operator: string, when: string}>  $changes
     */
    public function __construct(
        public string $recipientName,
        public array $changes,
        public string $businessName,
        public string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->changes);

        return new Envelope(
            subject: 'Tu calendario se actualizó — '.$count.' '.($count === 1 ? 'cambio' : 'cambios'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.google-calendar-updated',
            with: [
                'recipientName' => $this->recipientName,
                'changes' => $this->changes,
                'businessName' => $this->businessName,
                'appUrl' => $this->appUrl,
            ],
        );
    }
}
