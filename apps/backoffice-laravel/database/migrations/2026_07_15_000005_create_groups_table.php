<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Grupo" — combo de Servicios + Artículos con cantidad (ej. "Corte de cola" = 0.5 hrs
     * veterinario + 5 vendas). Sin caché de precio: se calcula al vuelo con SUM() sobre sus
     * componentes vigentes (ver Group::calculatedPrice()), igual que Account::balance() —
     * así un cambio de precio en el catálogo se refleja automático sin invalidar nada.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
