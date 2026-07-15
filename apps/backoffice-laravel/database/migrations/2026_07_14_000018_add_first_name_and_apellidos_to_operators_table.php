<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('full_name');
            $table->string('apellido_paterno')->nullable()->after('first_name');
            $table->string('apellido_materno')->nullable()->after('apellido_paterno');
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'apellido_paterno', 'apellido_materno']);
        });
    }
};
