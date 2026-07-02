<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users.operator_role_id existe en producción (aplicado fuera de migración en algún momento
     * previo) pero nunca tuvo una migración propia — 2026_06_30_000001 asume la columna ya
     * presente vía ->after('operator_role_id'). Esta migración cierra ese hueco de forma
     * idempotente para que una base nueva (ej. `testing`) pueda migrar desde cero.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'operator_role_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('operator_role_id')
                ->nullable()
                ->constrained('operator_roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'operator_role_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\OperatorRole::class);
            $table->dropColumn('operator_role_id');
        });
    }
};
