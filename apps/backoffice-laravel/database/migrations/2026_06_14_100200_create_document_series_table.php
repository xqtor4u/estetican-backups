<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 30); // recibo, factura, sin_documento
            $table->string('name');              // nombre descriptivo, ej. "Recibos generales"
            $table->string('prefix', 10)->default(''); // R-, F-, SD-
            $table->string('suffix', 10)->default(''); // para uso futuro
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(4); // dígitos con cero, ej. 4 → 0001
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // nulo = global
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['document_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_series');
    }
};
