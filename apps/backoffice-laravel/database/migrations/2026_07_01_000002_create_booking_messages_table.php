<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spa_booking_id')->constrained('spa_bookings')->cascadeOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->string('phone_number');
            $table->text('message_body');
            $table->text('wa_link');
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('sent_at');
            $table->timestamps();

            $table->index(['spa_booking_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_messages');
    }
};
