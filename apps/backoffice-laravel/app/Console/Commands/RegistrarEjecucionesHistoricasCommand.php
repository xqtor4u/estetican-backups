<?php

namespace App\Console\Commands;

use App\Domain\Execution\Services\BookingExecutionRecorder;
use App\Models\ExecutedService;
use App\Models\SpaBooking;
use Illuminate\Console\Command;

class RegistrarEjecucionesHistoricasCommand extends Command
{
    protected $signature = 'ejecuciones:registrar-historico {--dry-run : Solo cuenta, sin escribir}';

    protected $description = 'B4: registra en executed_services las citas completadas antes de que existiera el registro automático, con los datos actuales de cada cita (lo mejor disponible para el pasado). Idempotente: solo procesa citas completadas sin registro.';

    public function handle(BookingExecutionRecorder $recorder): int
    {
        $pending = SpaBooking::where('status', 'completed')
            ->whereNotIn('id', ExecutedService::whereNotNull('spa_booking_id')->select('spa_booking_id'))
            ->orderBy('id')
            ->get();

        $this->info("Citas completadas sin registro de ejecución: {$pending->count()}");

        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] No se escribió nada.');

            return self::SUCCESS;
        }

        foreach ($pending as $booking) {
            // Fecha real aproximada de cierre: la última vez que se tocó la cita.
            $recorder->record($booking, $booking->updated_at ?? $booking->scheduled_at);
        }

        $this->info("Registradas: {$pending->count()}");

        return self::SUCCESS;
    }
}
