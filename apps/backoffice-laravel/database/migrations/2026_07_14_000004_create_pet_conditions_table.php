<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icd_code')->nullable();
            $table->enum('status', ['active', 'controlled', 'resolved', 'chronic_monitoring'])->default('active');
            $table->date('onset_date')->nullable();
            $table->date('resolved_date')->nullable();
            // promoted_from_diagnosis_id se agrega en una migración posterior, una vez existe clinical_diagnoses
            $table->text('notes')->nullable();
            $table->foreignId('medical_alert_id')->nullable()->constrained('pet_medical_alerts')->nullOnDelete();
            $table->timestamps();

            $table->index(['pet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_conditions');
    }
};
