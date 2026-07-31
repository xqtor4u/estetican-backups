<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->foreignId('operator_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
            $table->boolean('is_external')->default(false)->after('operator_id');
            $table->decimal('external_cost', 10, 2)->nullable()->after('is_external')->comment('Costo del proveedor externo, solo aplica si is_external=true');
        });
    }

    public function down(): void
    {
        Schema::table('spa_booking_services', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropColumn(['operator_id', 'is_external', 'external_cost']);
        });
    }
};
