<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('ai_visible')->default(false)->after('is_core_vaccine');
            $table->boolean('is_generic')->default(false)->after('ai_visible');
            $table->boolean('is_emergency')->default(false)->after('is_generic');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['ai_visible', 'is_generic', 'is_emergency']);
        });
    }
};
