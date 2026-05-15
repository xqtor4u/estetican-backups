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
        Schema::create('cash_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('Efectivo');
            $table->string('category')->default('payment'); // e.g. advance, liquidation, misc_charge
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method'); // Tarjeta, Transferencia, etc.
            $table->string('external_reference')->nullable();
            $table->decimal('processing_fee', 10, 2)->default(0);
            $table->string('category')->default('payment'); // e.g. advance, liquidation, misc_charge
            $table->text('notes')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_ledgers');
        Schema::dropIfExists('cash_ledgers');
    }
};
