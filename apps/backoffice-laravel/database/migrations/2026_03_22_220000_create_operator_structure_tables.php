<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('operator_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('operator_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_role_id')->constrained()->cascadeOnDelete();
            $table->string('proficiency_level')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['operator_id', 'operator_role_id'], 'operator_role_unique_assignment');
        });

        Schema::create('operator_branch_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['operator_id', 'branch_id'], 'operator_branch_unique_assignment');
        });

        Schema::create('operator_compensation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->string('compensation_type')->default('hourly');
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_compensation_profiles');
        Schema::dropIfExists('operator_branch_assignments');
        Schema::dropIfExists('operator_role_assignments');
        Schema::dropIfExists('operator_roles');
        Schema::dropIfExists('branches');
    }
};