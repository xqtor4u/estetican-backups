<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('current_price')
                ->comment('Cuándo arrancó esta línea en particular — permite iniciar algunos servicios de la cita antes que otros');
        });
    }

    public function down(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
