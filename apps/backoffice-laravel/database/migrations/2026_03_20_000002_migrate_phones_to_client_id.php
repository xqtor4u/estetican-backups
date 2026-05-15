<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrar datos existentes de phoneable_id a client_id
        \DB::statement('UPDATE phones SET client_id = phoneable_id WHERE phoneable_type = "App\\Models\\Client"');
    }

    public function down(): void
    {
        // Revertir: restaurar phoneable_id desde client_id
        \DB::statement('UPDATE phones SET phoneable_id = client_id, phoneable_type = "App\\Models\\Client" WHERE client_id IS NOT NULL');
    }
};
