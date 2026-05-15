<?php
// database/migrations/2026_04_25_020931_rename_is_fiscal_to_destination_in_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Eliminamos el campo temporal anterior y creamos el nuevo con el lenguaje correcto
            $table->dropColumn('is_fiscal');
            $table->string('destination')->default('caja')->after('payment_method'); // 'caja' o 'banco'
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('destination');
            $table->boolean('is_fiscal')->default(false)->after('payment_method');
        });
    }
};
