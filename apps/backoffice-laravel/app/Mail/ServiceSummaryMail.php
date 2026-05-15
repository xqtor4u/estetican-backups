<?php

namespace App\Mail;

use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServiceSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $settings;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SpaBooking $booking
    ) {
        $systemSettings = app(SystemSettings::class);
        $this->settings = [
            'branding' => $systemSettings->get('branding'),
            'fiscal' => $systemSettings->get('fiscal'),
            'operational' => $systemSettings->get('operational'),
        ];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = ($this->settings['operational']['operational_email_subject_prefix'] ?? 'Resumen de Servicio') . ' - ' . $this->booking->pet->name;
        
        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.service-summary',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
