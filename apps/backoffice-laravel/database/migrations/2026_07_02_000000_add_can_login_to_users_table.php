<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users.can_login existe en producción pero nunca tuvo migración propia (mismo patrón
     * que NT-013 con operator_role_id). Cierra el hueco de forma idempotente para que una
     * base nueva (ej. `testing`) pueda migrar desde cero.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'can_login')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_login')->default(true);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'can_login')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_login');
        });
    }
};
