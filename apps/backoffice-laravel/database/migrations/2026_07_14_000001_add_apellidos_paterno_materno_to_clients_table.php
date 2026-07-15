<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('apellido_paterno')->nullable()->after('last_name');
            $table->string('apellido_materno')->nullable()->after('apellido_paterno');
            $table->index(['apellido_paterno', 'first_name'], 'clients_apellido_paterno_first_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_apellido_paterno_first_name_idx');
            $table->dropColumn(['apellido_paterno', 'apellido_materno']);
        });
    }
};
