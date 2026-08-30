<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->unsignedSmallInteger('scheduled_offset_minutes')->default(0)->after('operator_id')
                ->comment('Minutos desde spa_bookings.scheduled_at en que arranca esta línea. 0 = al inicio de la cita. Permite dejar huecos entre servicios (agendado móvil, SYNC-068). Retrocompatible: las líneas viejas quedan en 0 = pegadas al inicio.');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('scheduled_offset_minutes')
                ->comment('Duración real de esta línea al agendar (editable, stepper ±5). NULL = usar la del catálogo (services.duration_minutes). Antes SYNC-043 solo la usaba para validar, no la persistía.');
        });
    }

    public function down(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->dropColumn(['scheduled_offset_minutes', 'duration_minutes']);
        });
    }
};
