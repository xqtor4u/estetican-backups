<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight_kg', 6, 2);
            $table->dateTime('measured_at');
            $table->foreignId('recorded_by_operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->enum('source', ['clinical_visit', 'grooming_checkin', 'manual'])->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pet_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_weights');
    }
};
