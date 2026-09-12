<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** SYNC-105 (portado desde Zeus/ZEUS-037): mismo hueco que spa_booking_services, para la línea paralela de artículos. */
    public function up(): void
    {
        Schema::table('spa_booking_items', function (Blueprint $table) {
            $table->string('item_name_snapshot')->nullable()->after('item_id');
        });

        DB::table('spa_booking_items')
            ->join('items', 'items.id', '=', 'spa_booking_items.item_id')
            ->update(['spa_booking_items.item_name_snapshot' => DB::raw('items.name')]);
    }

    public function down(): void
    {
        Schema::table('spa_booking_items', function (Blueprint $table) {
            $table->dropColumn('item_name_snapshot');
        });
    }
};
