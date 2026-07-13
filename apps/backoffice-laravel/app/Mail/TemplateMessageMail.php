<?php

namespace App\Mail;

use App\Models\Client;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TemplateMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $settings;

    public string $preferencesUrl;

    public function __construct(
        public string $emailSubject,
        public string $messageBody,
        public Client $client,
    ) {
        $this->settings = app(SystemSettings::class)->all();
        $this->preferencesUrl = URL::temporarySignedRoute('client-preferences.show', now()->addYear(), ['client' => $client->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.template-message',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
