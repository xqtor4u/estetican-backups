<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('diagnosis');
            $table->enum('diagnosis_type', ['presumptive', 'definitive', 'differential', 'ruled_out'])->default('presumptive');
            $table->string('icd_code')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('promoted_to_condition_id')->nullable()->constrained('pet_conditions')->nullOnDelete();
            $table->timestamps();

            $table->index(['pet_id', 'clinical_visit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_diagnoses');
    }
};
