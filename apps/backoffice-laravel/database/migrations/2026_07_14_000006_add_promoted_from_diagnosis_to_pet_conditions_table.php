<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pet_conditions', function (Blueprint $table) {
            $table->foreignId('promoted_from_diagnosis_id')->nullable()->after('resolved_date')
                ->constrained('clinical_diagnoses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pet_conditions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promoted_from_diagnosis_id');
        });
    }
};
