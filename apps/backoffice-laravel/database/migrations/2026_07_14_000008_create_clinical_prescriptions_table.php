<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescribed_by_operator_id')->constrained('operators')->restrictOnDelete();
            $table->dateTime('prescribed_at');
            $table->text('general_instructions')->nullable();
            $table->timestamps();

            $table->index(['pet_id', 'clinical_visit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_prescriptions');
    }
};
