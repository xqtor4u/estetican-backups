<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pet_vaccinations', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('service_id')
                ->constrained('items')->nullOnDelete();
            $table->boolean('is_external')->default(false)->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('pet_vaccinations', function (Blueprint $table) {
            $table->dropColumn('is_external');
            $table->dropConstrainedForeignId('item_id');
        });
    }
};
