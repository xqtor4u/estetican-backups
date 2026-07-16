<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paralela a `spa_booking_services` — líneas de artículo congeladas al aceptar una
     * cotización (ver QuoteService::acceptQuote()). `current_price` es el total de la línea
     * (cantidad ya aplicada), mismo significado que en `spa_booking_services.current_price`.
     */
    public function up(): void
    {
        Schema::create('spa_booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('current_price', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spa_booking_items');
    }
};
