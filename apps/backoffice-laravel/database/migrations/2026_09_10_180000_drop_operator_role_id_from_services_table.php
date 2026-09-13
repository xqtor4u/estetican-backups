<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SYNC-103 (Fase 3 de SYNC-073, §8: "eliminar `services.operator_role_id` cuando nada la lea").
 * Migración "contract" — invierte exactamente `2026_03_23_120000_add_operator_role_id_to_services_table`.
 * La elegibilidad real de un operador para un servicio nunca leyó esta columna
 * (`OperatorServiceResolver::canPerform()` usa `operator_role_service_template` +
 * `operator_service_capabilities` + `open_to_all_operators`); era solo un campo requerido del
 * formulario web y de la API móvil, ya limpiado en el mismo commit que esta migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operator_role_id');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('operator_role_id')
                ->nullable()
                ->after('code')
                ->constrained('operator_roles')
                ->nullOnDelete();
        });
    }
};
