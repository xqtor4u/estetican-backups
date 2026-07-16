<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `stock_quantity` (BL-051) nació unsigned. Con el ledger `item_movements` (BL-049) el saldo
     * puede quedar negativo de verdad (ej. se registró un consumo antes de capturar la entrada
     * correspondiente) — eso es información válida ("hay que hacer un ajuste"), no un error a
     * esconder. Se vuelve signed para poder guardar ese caso sin tronar.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->default(0)->change();
        });
    }
};
