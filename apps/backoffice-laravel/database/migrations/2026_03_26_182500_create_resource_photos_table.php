<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->string('photo_url');
            $table->string('photo_type', 255)->default('evidencia');
            $table->dateTime('taken_at')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['resource_id', 'is_primary']);
            $table->index(['resource_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_photos');
    }
};