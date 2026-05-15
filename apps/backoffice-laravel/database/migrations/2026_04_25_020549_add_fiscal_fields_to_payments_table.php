<?php
// database/migrations/2026_04_25_020549_add_fiscal_fields_to_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_fiscal')->default(false)->after('payment_method');
            $table->decimal('processing_fee', 10, 2)->default(0)->after('amount');
            $table->string('external_reference')->nullable()->after('is_fiscal'); // ID de terminal o Mercado Pago
            $table->timestamp('cleared_at')->nullable()->after('updated_at'); // Fecha real de depósito en banco
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['is_fiscal', 'processing_fee', 'external_reference', 'cleared_at']);
        });
    }
};
