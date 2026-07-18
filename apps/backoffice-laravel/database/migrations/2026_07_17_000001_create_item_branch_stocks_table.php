<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock por sucursal real (BL-049, primera pieza): saldo cacheado por (item, branch),
     * mantenido por ItemMovementService recalculando SUM(quantity) de item_movements filtrado
     * por branch_id — mismo espíritu de caché derivado que items.stock_quantity (global).
     *
     * branch_id es NOT NULL aquí (a diferencia de item_movements.branch_id, que sí es nullable):
     * MySQL trata cada NULL como distinto en un índice único compuesto, así que
     * unique(item_id, branch_id) no protegería contra duplicados de "sin sucursal". Los movimientos
     * sin sucursal (ej. consumo automático de vacunas) no generan fila aquí — su saldo se deriva
     * por resta (items.stock_quantity - SUM(item_branch_stocks.quantity)) donde se necesite mostrar.
     *
     * cascadeOnDelete en ambas FKs (a diferencia de item_movements, que usa nullOnDelete por ser
     * ledger histórico): esta tabla es un caché derivado sin valor histórico propio.
     */
    public function up(): void
    {
        Schema::create('item_branch_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0); // signed, mismo motivo que items.stock_quantity
            $table->timestamps();

            $table->unique(['item_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_branch_stocks');
    }
};
