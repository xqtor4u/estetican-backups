<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_prescription_items', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('clinical_prescription_id')
                ->constrained('items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_prescription_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
        });
    }
};
