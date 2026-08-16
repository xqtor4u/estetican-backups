<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CashCierresMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $report,
        public string $pdfContent,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cierres de turno — ' . $this->report['dateFrom'] . ' a ' . $this->report['dateTo'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cash-cierres',
            with: ['report' => $this->report],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
