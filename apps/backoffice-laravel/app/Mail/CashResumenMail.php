<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CashResumenMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array $report Mismo array que arma CashController::buildResumenData() */
    public function __construct(
        public array $report,
        public string $pdfContent,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resumen de caja — ' . $this->report['dateFrom'] . ' a ' . $this->report['dateTo'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cash-resumen',
            with: ['report' => $this->report],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
