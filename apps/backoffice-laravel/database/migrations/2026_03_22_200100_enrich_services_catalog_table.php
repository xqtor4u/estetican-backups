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
        Schema::table('services', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->decimal('suggested_price', 10, 2)->nullable()->after('price');
            $table->integer('suggested_duration_minutes')->nullable()->after('duration_minutes');
            $table->boolean('is_active')->default(true)->after('suggested_duration_minutes');
        });

        DB::table('services')->update([
            'suggested_price' => DB::raw('price'),
            'suggested_duration_minutes' => DB::raw('duration_minutes'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'suggested_price',
                'suggested_duration_minutes',
                'is_active',
            ]);
        });
    }
};