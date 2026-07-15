<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_prescription_id')->constrained()->cascadeOnDelete();
            $table->string('drug_name');
            $table->string('concentration')->nullable();
            $table->string('dose');
            $table->enum('route', ['oral', 'topical', 'subcutaneous', 'intramuscular', 'intravenous', 'ophthalmic', 'otic', 'other'])->default('oral');
            $table->string('frequency');
            $table->unsignedInteger('duration_days')->nullable();
            $table->text('special_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_prescription_items');
    }
};
