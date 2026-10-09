<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ZEUS-040 (encontrado en la batería de UI/UX, 12/09/2026): tstmov tenía su propio timeout
     * de bloqueo guardado solo en localStorage del navegador (default 5 min), completamente
     * independiente de `screen_lock_idle_minutes` (el ajuste del backoffice web) — configurar
     * "Nunca" en web no apagaba el bloqueo en el celular, y nada en la interfaz avisaba que eran
     * dos ajustes distintos. Decisión de Tomas: son dos preferencias reales y separadas (tiene
     * sentido que el bloqueo en la calle sea más corto que en el escritorio), pero la del móvil
     * debe vivir en el servidor — el backoffice web debe poder verla y editarla también, no solo
     * la app móvil.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'mobile_screen_lock_idle_minutes')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('mobile_screen_lock_idle_minutes')->nullable()->after('screen_lock_idle_minutes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'mobile_screen_lock_idle_minutes')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile_screen_lock_idle_minutes');
        });
    }
};
