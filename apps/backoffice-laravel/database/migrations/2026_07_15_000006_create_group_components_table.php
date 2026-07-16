<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Componentes de un Grupo: exactamente uno de service_id/item_id (CHECK constraint).
     * FKs reales en vez de morphs (`ItemMovement`) porque el universo de "tipo de componente"
     * es cerrado — solo Servicio o Artículo, confirmado con el usuario. restrictOnDelete en
     * vez de nullOnDelete: con el CHECK exigiendo uno no-nulo, un nullOnDelete podría dejar
     * una fila con ambos en NULL — mejor impedir borrar un Service/Item usado como componente
     * (ver guardas en ItemController/ServiceController::destroy()).
     */
    public function up(): void
    {
        Schema::create('group_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE group_components ADD CONSTRAINT chk_group_component_target
             CHECK ((service_id IS NOT NULL AND item_id IS NULL) OR (service_id IS NULL AND item_id IS NOT NULL))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE group_components DROP CONSTRAINT chk_group_component_target');
        Schema::dropIfExists('group_components');
    }
};
