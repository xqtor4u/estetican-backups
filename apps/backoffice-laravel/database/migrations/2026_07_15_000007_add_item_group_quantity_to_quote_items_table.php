<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `quote_items` gana soporte para líneas de artículo (además de servicio) y cantidad —
     * cimiento de "Grupos". `service_id` se vuelve nullable (exactamente uno de service_id/
     * item_id, CHECK constraint). `group_id` es trazabilidad barata (nullOnDelete): saber que
     * una línea vino de aplicar tal Grupo, sin volver la línea rígida ni depender de que el
     * Grupo siga existiendo.
     */
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->change();
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->after('quote_id')->constrained('groups')->nullOnDelete();
            $table->decimal('quantity', 8, 2)->default(1)->after('item_id');
        });

        DB::statement(
            'ALTER TABLE quote_items ADD CONSTRAINT chk_quote_item_target
             CHECK ((service_id IS NOT NULL AND item_id IS NULL) OR (service_id IS NULL AND item_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE quote_items DROP CONSTRAINT chk_quote_item_target');

        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['group_id']);
            $table->dropColumn(['item_id', 'group_id', 'quantity']);
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable(false)->change();
        });
    }
};
