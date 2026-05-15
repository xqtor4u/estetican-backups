<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar teléfonos sin client_id (no asociados a clientes)
        \DB::table('phones')->whereNull('client_id')->delete();
        // Eliminar columnas polimórficas
        Schema::table('phones', function (Blueprint $table) {
            $table->dropColumn(['phoneable_id', 'phoneable_type']);
        });
    }

    public function down(): void
    {
        Schema::table('phones', function (Blueprint $table) {
            $table->unsignedBigInteger('phoneable_id')->nullable();
            $table->string('phoneable_type')->nullable();
        });
    }
};
