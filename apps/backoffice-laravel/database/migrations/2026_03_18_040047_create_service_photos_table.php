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
        Schema::create('service_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('executed_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stay_id')->nullable()->constrained()->nullOnDelete();
            $table->string('photo_url');
            $table->string('stage');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_photos');
    }
};
