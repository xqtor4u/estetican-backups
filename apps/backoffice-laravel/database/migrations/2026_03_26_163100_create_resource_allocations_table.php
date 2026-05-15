<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_allocation_id')->nullable()->constrained('resource_allocations')->nullOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('allocation_type', 60);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->nullableMorphs('source');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'starts_at']);
            $table->index(['resource_id', 'ends_at']);
            $table->index(['resource_id', 'allocation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_allocations');
    }
};