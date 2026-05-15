<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('last_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('clients')->whereNull('last_name')->update(['last_name' => '']);

        Schema::table('clients', function (Blueprint $table): void {
            $table->string('last_name')->nullable(false)->default('')->change();
        });
    }
};