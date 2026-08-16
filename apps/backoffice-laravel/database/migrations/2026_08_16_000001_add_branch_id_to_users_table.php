<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sucursal asignada al usuario — dato organizacional persistente, sin relación con el
 * check-in del día (que es solo asistencia). Reemplaza al check-in como fuente de verdad
 * de "a qué sucursal pertenece este operador" para Caja y cualquier otro módulo que lo
 * necesite en el futuro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('operator_id')
                ->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
