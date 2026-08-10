<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_personal_email')->nullable()->after('notes');
            $table->string('google_calendar_visibility')->default('personal')->after('google_personal_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_personal_email', 'google_calendar_visibility']);
        });
    }
};
