<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')
                ->nullable()
                ->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
