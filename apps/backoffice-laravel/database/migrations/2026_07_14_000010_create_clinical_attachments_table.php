<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->enum('attachment_type', ['lab_result', 'xray', 'ultrasound', 'other_imaging', 'referral_letter', 'other'])->default('other');
            $table->string('file_path');
            $table->string('file_mime_type')->nullable();
            $table->text('description')->nullable();
            $table->date('performed_at')->nullable();
            $table->string('performed_by')->nullable();
            $table->foreignId('uploaded_by_operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->timestamps();

            $table->index(['pet_id', 'clinical_visit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_attachments');
    }
};
