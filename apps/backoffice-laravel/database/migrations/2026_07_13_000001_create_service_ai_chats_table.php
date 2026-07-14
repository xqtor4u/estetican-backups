<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_ai_chats', function (Blueprint $table) {
            $table->id();
            $table->string('session_uuid')->unique();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ai_chats');
    }
};
