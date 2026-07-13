<?php

namespace App\Mail;

use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ServiceSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $settings;

    public ?string $preferencesUrl = null;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SpaBooking $booking
    ) {
        if ($client = $this->booking->pet?->client) {
            $this->preferencesUrl = URL::temporarySignedRoute('client-preferences.show', now()->addYear(), ['client' => $client->id]);
        }

        // SystemSettings no tiene un método get(section) — solo all() (todas las
        // claves en un solo array plano) — ver NT-029. Se arma acá la forma anidada
        // que espera la vista, tomando cada clave del array plano.
        $flat = app(SystemSettings::class)->all();
        $this->settings = [
            'branding' => [
                'brand_logo_web' => $flat['brand_logo_web'] ?? null,
                'brand_business_name' => $flat['brand_business_name'] ?? null,
                'brand_address' => $flat['brand_address'] ?? null,
                'brand_phone' => $flat['brand_phone'] ?? null,
                'brand_url' => $flat['brand_url'] ?? null,
            ],
            'fiscal' => [
                'fiscal_business_name' => $flat['fiscal_business_name'] ?? null,
                'fiscal_rfc' => $flat['fiscal_rfc'] ?? null,
            ],
            'operational' => [
                'operational_email_signature_text' => $flat['operational_email_signature_text'] ?? null,
                'operational_email_subject_prefix' => $flat['operational_email_subject_prefix'] ?? null,
            ],
        ];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = ($this->settings['operational']['operational_email_subject_prefix'] ?? 'Resumen de Servicio').' - '.$this->booking->pet->name;

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
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
