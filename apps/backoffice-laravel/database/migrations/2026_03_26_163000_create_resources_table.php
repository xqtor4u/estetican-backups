<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type', 60)->default('cage');
            $table->string('code', 60);
            $table->string('name', 120);
            $table->string('capacity_label', 80)->nullable();
            $table->string('administrative_status', 60)->default('active');
            $table->string('operational_status', 60)->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
            $table->index(['resource_type', 'operational_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};