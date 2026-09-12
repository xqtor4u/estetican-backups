<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SYNC-105 (portado desde Zeus/ZEUS-037): `current_price` ya se congelaba bien al crear la
     * línea, pero el nombre del servicio siempre se leía en vivo vía la relación — un servicio
     * renombrado cambiaba en silencio la descripción de recibos ya emitidos cada vez que se
     * reimprimían.
     */
    public function up(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->string('service_name_snapshot')->nullable()->after('service_id');
        });

        DB::table('spa_booking_services')
            ->join('services', 'services.id', '=', 'spa_booking_services.service_id')
            ->update(['spa_booking_services.service_name_snapshot' => DB::raw('services.name')]);
    }

    public function down(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->dropColumn('service_name_snapshot');
        });
    }
};
