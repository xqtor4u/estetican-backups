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
