<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operator_roles', function (Blueprint $table) {
            $table->char('acronym', 3)->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('operator_roles', function (Blueprint $table) {
            $table->dropUnique(['acronym']);
            $table->dropColumn('acronym');
        });
    }
};
