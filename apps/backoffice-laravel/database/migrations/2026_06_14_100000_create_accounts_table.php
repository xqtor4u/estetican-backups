<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 20); // activo, pasivo, capital, ingreso, gasto
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('allows_entries')->default(true); // false = solo agrupadora
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
