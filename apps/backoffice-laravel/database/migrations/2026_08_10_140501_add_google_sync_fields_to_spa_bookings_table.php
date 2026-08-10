<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('order_folio');
            $table->timestamp('google_synced_at')->nullable()->after('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn(['google_event_id', 'google_synced_at']);
        });
    }
};
