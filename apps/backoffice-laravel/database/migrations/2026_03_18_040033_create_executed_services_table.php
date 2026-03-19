<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('executed_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->decimal('final_price', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('executed_services');
    }
};
