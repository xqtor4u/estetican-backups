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
        Schema::table('executed_service_items', function (Blueprint $table) {
            $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_external')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('executed_service_items', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropColumn(['operator_id', 'is_external']);
        });
    }
};
