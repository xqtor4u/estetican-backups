<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->string('google_calendar_id')->nullable()->after('notes');
            $table->string('google_personal_email')->nullable()->after('google_calendar_id');
            $table->boolean('google_calendar_share_enabled')->default(false)->after('google_personal_email');
            $table->timestamp('google_calendar_shared_at')->nullable()->after('google_calendar_share_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropColumn([
                'google_calendar_id',
                'google_personal_email',
                'google_calendar_share_enabled',
                'google_calendar_shared_at',
            ]);
        });
    }
};
