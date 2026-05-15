<?php
// database/migrations/2026_04_25_021331_add_advance_rules_to_services_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('requires_advance')->default(false)->after('price');
            $table->decimal('advance_percentage', 5, 2)->nullable()->after('requires_advance');
            $table->integer('lead_time_hours')->default(0)->after('advance_percentage'); // Útil para Tienda o preparación
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['requires_advance', 'advance_percentage', 'lead_time_hours']);
        });
    }
};
