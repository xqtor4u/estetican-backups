<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
//
// SYNC-079 — el grueso del trabajo lo dispara SpaBookingObserver /
// SpaBookingServiceObserver: encolan SyncBookingToGoogleJob en la cola
// `google-calendar` en cuanto cambia una cita. Aquí solo se drena esa cola, una
// vez por minuto y SOLO si tiene algo — así no se arranca un proceso PHP en vano
// 1440 veces al día. El `schedule:run` del host ya corre cada minuto.
Schedule::command('queue:work', [
    '--queue' => 'google-calendar',
    '--stop-when-empty',
    '--max-time' => 50,
    '--sleep' => 0,
    '--tries' => 3,
])
    ->everyMinute()
    ->withoutOverlapping()
    ->when(function () {
        try {
            return DB::table('jobs')->where('queue', 'google-calendar')->exists();
        } catch (Throwable) {
            return false;
        }
    });

// Barrido de respaldo: aprovisiona calendarios (crearlos y compartirlos con
// operadores y viewers) y reconcilia lo que el camino por eventos no alcanzó
// (worker caído, error de Google, escritura directa a BD). Con el observer en
// tiempo real casi siempre encuentra 0 citas pendientes. Antes: cada 5 min.
Schedule::command('calendario:sincronizar-google')->hourly()->withoutOverlapping();
