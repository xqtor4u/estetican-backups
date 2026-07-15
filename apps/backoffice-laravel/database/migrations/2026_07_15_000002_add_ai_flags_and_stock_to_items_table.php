<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos mínimos para que `items` pueda entrar al catálogo del asistente IA (venta vía
     * WhatsApp/redes) sin construir el módulo de inventario real (BL-049) todavía.
     * `stock_quantity` es un contador simple editable a mano, sin movimientos/almacenes/histórico
     * — default 0 a propósito: hasta que se capture existencia real, el artículo no es visible
     * para el asistente (ver filtro en ServiceCatalogPromptBuilder).
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('ai_visible')->default(false)->after('is_active');
            $table->unsignedInteger('stock_quantity')->default(0)->after('ai_visible');
            $table->decimal('price', 10, 2)->nullable()->after('presentation');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['ai_visible', 'stock_quantity', 'price']);
        });
    }
};
