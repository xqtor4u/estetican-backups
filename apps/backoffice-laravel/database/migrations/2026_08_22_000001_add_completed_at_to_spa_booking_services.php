<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('started_at')
                ->comment('Cuándo terminó esta línea en particular — independiente de cobrar/cerrar toda la cita');
        });
    }

    public function down(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
