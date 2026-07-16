<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Costo de compra — base para sugerir `price` (precio de venta) según el margen de
     * utilidad configurable en Configuración del sistema → Tienda y Proyectos.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('cost_price', 10, 2)->nullable()->after('presentation');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
