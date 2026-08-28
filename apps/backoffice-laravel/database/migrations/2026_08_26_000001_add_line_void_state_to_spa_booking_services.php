<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('completed_at')
                ->comment('La línea se canceló — no se va a hacer. Excluye la línea del total a cobrar de la cita.');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at')
                ->comment('Motivo opcional de la cancelación de la línea.');
            $table->timestamp('not_performed_at')->nullable()->after('cancellation_reason')
                ->comment('La línea no se realizó (estaba pero no se hizo). Excluye la línea del total a cobrar.');
            $table->string('not_performed_reason')->nullable()->after('not_performed_at')
                ->comment('Motivo opcional de que la línea no se realizara.');
        });
    }

    public function down(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancellation_reason', 'not_performed_at', 'not_performed_reason']);
        });
    }
};
