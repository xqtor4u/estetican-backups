<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZEUS-047 (07/10/2026):
 * - `branches.operating_days`: días que abre la sucursal ("1,2,3,4,5,6", Carbon::dayOfWeek,
 *   0 = domingo). Null = los días generales de Configuración (`booking_open_*`).
 * - `spa_bookings.series_confirmed_at`: botón "Fijar" — confirma una sola cita de una serie
 *   pre-programada sin esperar a que se confirme la serie completa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('operating_days', 20)->nullable()->after('closing_time');
        });

        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->timestamp('series_confirmed_at')->nullable()->after('series_move_reason');
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn('series_confirmed_at');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('operating_days');
        });
    }
};
