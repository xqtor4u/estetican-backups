<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZEUS-047 Fase 3 (08/10/2026): ciclo de vida de las series recurrentes.
 *
 * - Pausa por rango (`paused_from`/`paused_until`) y cancelación "de aquí en adelante".
 * - Quién fijó cada cita (`spa_bookings.series_confirmed_by_user_id`).
 * - Bitácora `spa_booking_series_events`: las citas virtuales descartadas o perdidas se BORRAN
 *   de la agenda, así que el rastro (para el reporte diario por correo) vive aquí, sin FK a la cita.
 * - `users.email_notifications`: avisos por correo que cada usuario elige recibir
 *   (por ahora solo el reporte diario de series; ver EmailNotificationTypes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_booking_series', function (Blueprint $table) {
            $table->date('paused_from')->nullable()->after('ends_on');
            $table->date('paused_until')->nullable()->after('paused_from');
            $table->timestamp('cancelled_at')->nullable()->after('paused_until');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->foreignId('series_confirmed_by_user_id')->nullable()->after('series_confirmed_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('spa_booking_series_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('spa_booking_series')->cascadeOnDelete();
            // Sin FK: la cita puede haberse borrado (descartada / perdida).
            $table->unsignedBigInteger('spa_booking_id')->nullable();
            $table->string('type', 20);
            $table->dateTime('scheduled_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('details', 255)->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('email_notifications')->nullable()->after('google_calendar_notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_notifications');
        });

        Schema::dropIfExists('spa_booking_series_events');

        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_confirmed_by_user_id');
        });

        Schema::table('spa_booking_series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['paused_from', 'paused_until', 'cancelled_at']);
        });
    }
};
