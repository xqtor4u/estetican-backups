<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            // Usuario (dueño de la sesión, web o móvil) que agendó la cita
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('operator_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');
        });
    }
};
