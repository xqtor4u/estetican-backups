<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte de reversión para movimientos de caja — nunca se borra un movimiento ya
 * registrado (cada uno tiene un asiento contable real detrás), se revierte con un
 * movimiento contrario que cancela el efecto del original, dejando rastro de auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('notes');
            $table->foreignId('reversal_of_movement_id')->nullable()->after('reversed_at')
                ->constrained('cash_movements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_movement_id');
            $table->dropColumn('reversed_at');
        });
    }
};
