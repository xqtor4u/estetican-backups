<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cimiento del "IM sencillo" (BL-049): ledger append-only de movimientos de inventario,
     * mismo espíritu que `cash_ledgers`/`bank_ledgers` (sin saldo cacheado por renglón, el saldo
     * se deriva con SUM(quantity) — ver ItemMovementService). `items.stock_quantity` (BL-051)
     * pasa de editable a mano a caché mantenido por este ledger.
     *
     * `branch_id` nullable desde ya (no se fuerza a elegir sucursal hoy) para no tener que
     * rediseñar el esquema cuando el inventario sea realmente multi-sucursal. `reference_type`/
     * `reference_id` (morphs) es el gancho para que un consumo automático (ej. vacuna aplicada)
     * o una venta futura se liguen a su origen sin tocar esta tabla de nuevo.
     */
    public function up(): void
    {
        Schema::create('item_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name_snapshot');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // entrada, salida, consumo_servicio, ajuste, perdida — texto libre, sin enum en BD
            $table->integer('quantity'); // delta con signo: + entrada/ajuste positivo, - salida/consumo/pérdida/ajuste negativo
            $table->nullableMorphs('reference'); // ej. PetVaccination hoy; Payment/venta en el futuro
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_movements');
    }
};
