<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('allergen');
            $table->enum('allergen_type', ['food', 'medication', 'environmental', 'flea_tick', 'other'])->default('other');
            $table->text('reaction_description')->nullable();
            $table->enum('severity', ['mild', 'moderate', 'severe', 'anaphylaxis'])->default('mild');
            $table->date('diagnosed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('medical_alert_id')->nullable()->constrained('pet_medical_alerts')->nullOnDelete();
            $table->foreignId('clinical_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->timestamps();

            $table->index(['pet_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_allergies');
    }
};
