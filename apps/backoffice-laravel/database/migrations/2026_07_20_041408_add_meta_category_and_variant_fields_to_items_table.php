<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `meta_*` porque existen para satisfacer el contrato de la API de Meta (BL-052), no
     * son taxonomía de negocio propia (a diferencia de department/brand). Cada variante de
     * color es su propia fila de Item (stock/precio/foto ya son por-fila) — meta_variant_group
     * es solo la clave de texto que se manda tal cual como retailer_product_group_id, sin FK
     * real (Meta no nos devuelve nada que guardar, mismo criterio que retailer_id).
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('meta_category')->nullable()->after('department');
            $table->string('meta_variant_group')->nullable()->after('meta_category');
            $table->string('meta_color', 100)->nullable()->after('meta_variant_group');
            $table->index('meta_variant_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['meta_variant_group']);
            $table->dropColumn(['meta_category', 'meta_variant_group', 'meta_color']);
        });
    }
};
