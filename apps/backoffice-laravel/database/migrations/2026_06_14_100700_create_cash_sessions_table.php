<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            $table->foreignId('opened_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_amount', 12, 2)->default(0); // fondo de caja inicial

            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('closing_amount', 12, 2)->nullable();  // conteo físico al cierre
            $table->decimal('expected_amount', 12, 2)->nullable(); // calculado del sistema
            $table->decimal('difference', 12, 2)->nullable();      // closing - expected

            $table->string('status', 20)->default('abierta'); // abierta, cerrada
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cash_register_id', 'status']);
            $table->index(['branch_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
