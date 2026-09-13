<?php

use App\Domain\Planning\Services\OperatorServiceBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SYNC-073 — Capacidades de servicio por operador (m2m operador ↔ servicio).
 * Fase 1 (backend): 2 tablas nuevas + `services.open_to_all_operators` + backfill idempotente.
 * Aditiva y retrocompatible: `services.operator_role_id` se conserva (sólo fuente del backfill,
 * se elimina en una fase futura). Ver docs/tecnico/SPEC_CAPACIDADES_SERVICIO_POR_OPERADOR.md
 * (Zeus-Estetican).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_role_service_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_role_id')->constrained('operator_roles', indexName: 'orst_role_fk')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services', indexName: 'orst_service_fk')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['operator_role_id', 'service_id'], 'orst_role_service_unique');
        });

        Schema::create('operator_service_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('operators', indexName: 'osc_operator_fk')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services', indexName: 'osc_service_fk')->cascadeOnDelete();
            $table->string('mode', 16)->comment('grant = alta directa; revoke = baja directa (anula lo que aporte una plantilla de rol)');
            $table->string('note')->nullable()->comment('Por qué (auditoría)');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', indexName: 'osc_created_by_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['operator_id', 'service_id'], 'osc_operator_service_unique');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->boolean('open_to_all_operators')->default(false)->after('operator_role_id')
                ->comment('Toggle de admin (SYNC-073): true = cualquier operador puede agendar este servicio, se ignoran plantillas y capacidades. Sustituye al operator_role_id IS NULL como "abierto a todos" explícito.');
        });

        (new OperatorServiceBackfill)->run();
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('open_to_all_operators');
        });

        Schema::dropIfExists('operator_service_capabilities');
        Schema::dropIfExists('operator_role_service_template');
        // El retiro de CAP-BANO/CAP-CORTE del backfill no se revierte aquí: el rollback real se
        // hace restaurando el dump pre-migración (ver backups/LISTA_RESPALDOS.md), no con
        // `migrate:rollback`.
    }
};
