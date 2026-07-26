<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'screen_lock_idle_minutes')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('screen_lock_idle_minutes')->nullable()->after('can_login');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'screen_lock_idle_minutes')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('screen_lock_idle_minutes');
        });
    }
};
