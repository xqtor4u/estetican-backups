<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ZEUS-047 Fase 3: reporte diario de citas recurrentes (`series:cierre-diario`): atendidas,
 * fijadas, descartadas y perdidas, con quién lo hizo.
 */
class SeriesDailyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{attended: Collection, pinned: Collection, discarded: Collection, expired: Collection} $report */
    public function __construct(
        public string $recipientName,
        public Carbon $day,
        public array $report,
        public string $businessName,
        public string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Citas recurrentes del '.$this->day->format('d/m/Y').' — '.$this->report['attended']->count().' atendidas, '.$this->report['expired']->count().' perdidas',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.series-daily-report');
    }
}
