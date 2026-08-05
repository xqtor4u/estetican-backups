<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_messages', function (Blueprint $table) {
            $table->string('trigger')->default('manual')->after('channel');
            $table->string('provider_message_id')->nullable()->after('wa_link');
        });
    }

    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table) {
            $table->dropColumn(['trigger', 'provider_message_id']);
        });
    }
};
