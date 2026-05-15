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
        Schema::table('operators', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('code');
            $table->string('ine_number')->nullable()->after('full_name');
            $table->string('imss_number')->nullable()->after('ine_number');
            $table->text('address')->nullable()->after('imss_number');
            $table->string('phone')->nullable()->after('address');
            $table->string('emergency_contact_name')->nullable()->after('phone');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->date('hire_date')->nullable()->after('emergency_contact_phone');
        });

        DB::table('operators')
            ->whereNull('full_name')
            ->update([
                'full_name' => DB::raw('name'),
            ]);

        Schema::table('operators', function (Blueprint $table) {
            $table->string('full_name')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'ine_number',
                'imss_number',
                'address',
                'phone',
                'emergency_contact_name',
                'emergency_contact_phone',
                'hire_date',
            ]);
        });
    }
};