<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('operator_roles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->decimal('default_hourly_rate', 10, 2)->nullable()->after('description');
            $table->boolean('is_active')->default(true)->after('default_hourly_rate');
        });

        DB::table('operator_roles')
            ->whereNull('description')
            ->update([
                'description' => DB::raw('notes'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'default_hourly_rate', 'is_active']);
        });
    }
};