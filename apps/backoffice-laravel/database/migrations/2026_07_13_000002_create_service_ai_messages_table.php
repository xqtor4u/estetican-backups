<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_ai_chat_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['service_ai_chat_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ai_messages');
    }
};
