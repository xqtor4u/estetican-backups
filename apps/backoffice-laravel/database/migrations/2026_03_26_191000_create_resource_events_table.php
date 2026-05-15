<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('detected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('source');
            $table->string('event_type', 80);
            $table->string('event_status', 60)->default('open');
            $table->string('severity', 40)->default('medium');
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'event_status']);
            $table->index(['resource_id', 'event_type']);
            $table->index(['resource_id', 'severity']);
            $table->index(['resource_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_events');
    }
};