<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// BL-024b — recordatorios automáticos de cita. El cron del host que invoca
// `schedule:run` cada minuto es responsabilidad de infraestructura, no de este
// archivo (no existía ningún cron de Laravel antes de esto).
Schedule::command('whatsapp:enviar-recordatorios-cita')->everyFifteenMinutes()->withoutOverlapping();

// Sincronización de citas SPA a Google Calendar por operador, un solo sentido.
// Apagada por default (google_calendar_sync_enabled en SystemSettings) — no hace nada
// hasta que se configure la credencial de la cuenta de servicio y se active a propósito.
Schedule::command('calendario:sincronizar-google')->everyFiveMinutes()->withoutOverlapping();
