<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maestro de artículos — fundación atómica para el futuro módulo de Tienda/Inventario (BL-049).
     * Deliberadamente sin columnas de existencia/stock: esta tabla es solo identidad del producto
     * (nombre, marca, presentación, departamento), no manejo de inventario real todavía.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department')->nullable();
            $table->string('brand')->nullable();
            $table->string('presentation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
