<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('operators')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('visit_type', ['consultation', 'follow_up', 'emergency', 'pre_grooming_check', 'vaccination'])->default('consultation');
            $table->dateTime('visited_at');
            $table->text('reason_for_visit');

            // Subjetivo
            $table->text('subjective')->nullable();

            // Objetivo (semi-estructurado)
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('temperature_celsius', 4, 1)->nullable();
            $table->unsignedInteger('heart_rate_bpm')->nullable();
            $table->unsignedInteger('respiratory_rate_bpm')->nullable();
            $table->enum('mucous_membranes', ['pink', 'pale', 'cyanotic', 'icteric', 'congested'])->nullable();
            $table->enum('hydration_status', ['normal', 'mild_dehydration', 'moderate_dehydration', 'severe_dehydration'])->nullable();
            $table->unsignedTinyInteger('body_condition_score')->nullable();
            $table->text('objective_notes')->nullable();

            // Evaluación
            $table->text('assessment')->nullable();

            // Plan
            $table->text('plan')->nullable();
            $table->date('follow_up_at')->nullable();

            // Firma / inmutabilidad
            $table->enum('status', ['draft', 'signed', 'amended'])->default('draft');
            $table->foreignId('signed_by_operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->dateTime('signed_at')->nullable();
            $table->string('professional_license_snapshot')->nullable();
            $table->foreignId('amends_visit_id')->nullable()->constrained('clinical_visits')->nullOnDelete();
            $table->text('amendment_reason')->nullable();

            // Veterinario externo (BL clínico — atención fuera de EstetiCAN)
            $table->boolean('is_external')->default(false);
            $table->string('external_provider_name')->nullable();
            $table->string('external_provider_license')->nullable();
            $table->string('external_clinic_name')->nullable();
            $table->enum('external_status', ['pending_external_report', 'completed'])->nullable();

            $table->timestamps();

            $table->index(['pet_id', 'visited_at']);
            $table->index('status');
            $table->index('amends_visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_visits');
    }
};
