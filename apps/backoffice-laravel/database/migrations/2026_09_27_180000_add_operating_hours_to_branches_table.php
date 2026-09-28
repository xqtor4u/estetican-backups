<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2 (27/09/2026): horario operativo propio por sucursal ("HH:MM"). Null = usa el horario general
 * de Configuración (booking_opening_time/booking_closing_time) — aditiva, nada cambia hasta que
 * una sucursal tenga horario propio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('opening_time', 5)->nullable()->after('is_active');
            $table->string('closing_time', 5)->nullable()->after('opening_time');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['opening_time', 'closing_time']);
        });
    }
};
