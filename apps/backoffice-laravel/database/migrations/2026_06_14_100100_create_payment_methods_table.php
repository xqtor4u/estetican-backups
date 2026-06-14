<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20); // cash, card, transfer, crypto, gateway
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->json('gateway_config')->nullable(); // credenciales de pasarela (cifradas), futuro
            $table->boolean('requires_reference')->default(false); // SPEI, tarjeta
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
