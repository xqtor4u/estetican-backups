<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_event_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('update_type', 80)->default('note');
            $table->string('from_status', 60)->nullable();
            $table->string('to_status', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['resource_event_id', 'created_at']);
            $table->index(['resource_event_id', 'to_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_event_updates');
    }
};