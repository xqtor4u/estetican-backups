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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('street')->nullable()->after('name');
            $table->string('colonia')->nullable()->after('street');
            $table->string('city')->nullable()->after('colonia');
            $table->string('state')->nullable()->after('city');
            $table->string('zip')->nullable()->after('state');
            $table->string('country')->nullable()->after('zip');
            $table->decimal('lat', 10, 8)->nullable()->after('country');
            $table->decimal('lng', 11, 8)->nullable()->after('lat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'street',
                'colonia',
                'city',
                'state',
                'zip',
                'country',
                'lat',
                'lng',
            ]);
        });
    }
};