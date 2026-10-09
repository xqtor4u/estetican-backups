<?php

namespace App\Console\Commands;

use App\Domain\Planning\Series\SeriesLifecycleService;
use App\Mail\SeriesDailyReportMail;
use App\Support\Notifications\EmailNotificationTypes;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * ZEUS-047 Fase 3: cierre del día de las series recurrentes (programado a las 23:50).
 * Borra las citas virtuales que nadie fijó, marca terminadas las series vencidas y manda el
 * reporte del día a quien activó "Reporte diario de citas recurrentes" en su usuario.
 */
class CierreDiarioSeriesCommand extends Command
{
    protected $signature = 'series:cierre-diario {--fecha= : Día a cerrar (AAAA-MM-DD), por defecto hoy} {--sin-correo : No manda el reporte}';

    protected $description = 'Borra las citas de series recurrentes que nadie fijó y manda el reporte diario por correo.';

    public function handle(SeriesLifecycleService $lifecycle, SystemSettings $settings): int
    {
        $day = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : today();

        $expired = $lifecycle->closeDay($day);
        $this->info("{$expired} citas de series sin fijar borradas.");

        if ($this->option('sin-correo')) {
            return self::SUCCESS;
        }

        $report = $lifecycle->dailyReport($day);
        if (collect($report)->every->isEmpty()) {
            $this->line('Sin movimientos de series en el día: no se manda correo.');

            return self::SUCCESS;
        }

        $recipients = EmailNotificationTypes::recipients(EmailNotificationTypes::SERIES_DAILY);
        if ($recipients->isEmpty()) {
            return self::SUCCESS;
        }

        // ApplySystemSettings (SMTP de SystemSettings → config()) solo corre en HTTP.
        $overrides = $settings->configOverrides();
        if ($overrides !== []) {
            config($overrides);
        }

        $businessName = $settings->all()['brand_business_name'] ?? 'EstetiCAN';

        foreach ($recipients as $user) {
            try {
                Mail::to($user->email)->send(new SeriesDailyReportMail($user->first_name ?: $user->name, $day, $report, $businessName, (string) config('app.url')));
                $this->line("reporte enviado a {$user->email}");
            } catch (Throwable $e) {
                report($e);
                $this->error("no se pudo enviar a {$user->email}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
