<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZEUS-047 (07/10/2026): serie de citas recurrentes. Las citas se materializan desde el alta
 * (`spa_bookings.series_id`); la serie guarda la regla de repetición y la "plantilla" de la cita
 * (servicios/operadores/duraciones/precios/jaula) para poder extenderla después.
 *
 * `status`: pending_review (pre-programada: aparta horario, sin recordatorios) → active
 * (agendada) → ended/cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spa_booking_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('status', 20)->default('pending_review');
            $table->json('rule');
            $table->json('template');
            $table->dateTime('starts_at');
            $table->date('ends_on');
            $table->json('skipped_occurrences')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'ends_on']);
        });

        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('branch_id')
                ->constrained('spa_booking_series')->nullOnDelete();
            // Fecha/hora que dictaba la regla; distinta de scheduled_at solo si se recorrió.
            $table->dateTime('series_original_at')->nullable()->after('series_id');
            $table->string('series_move_reason', 190)->nullable()->after('series_original_at');
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn(['series_original_at', 'series_move_reason']);
        });

        Schema::dropIfExists('spa_booking_series');
    }
};
