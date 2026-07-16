<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->decimal('quantity', 8, 2)->default(1)->after('service_id');
            $table->foreignId('group_id')->nullable()->after('quantity')->constrained('groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn(['quantity', 'group_id']);
        });
    }
};
