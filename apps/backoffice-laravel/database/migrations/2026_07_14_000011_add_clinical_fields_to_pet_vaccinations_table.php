<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pet_vaccinations', function (Blueprint $table) {
            $table->string('lot_number')->nullable()->after('notes');
            $table->string('manufacturer')->nullable()->after('lot_number');
            $table->foreignId('administered_by_operator_id')->nullable()->after('manufacturer')
                ->constrained('operators')->nullOnDelete();
            $table->foreignId('clinical_visit_id')->nullable()->after('administered_by_operator_id')
                ->constrained('clinical_visits')->nullOnDelete();
            $table->unsignedInteger('dose_number')->nullable()->after('clinical_visit_id');
            $table->enum('route', ['subcutaneous', 'intramuscular', 'intranasal', 'oral'])->nullable()->after('dose_number');
            $table->string('site')->nullable()->after('route');
            $table->text('reaction_notes')->nullable()->after('site');

            $table->index(['pet_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pet_vaccinations', function (Blueprint $table) {
            $table->dropIndex(['pet_id', 'expires_at']);
            $table->dropConstrainedForeignId('clinical_visit_id');
            $table->dropConstrainedForeignId('administered_by_operator_id');
            $table->dropColumn(['lot_number', 'manufacturer', 'dose_number', 'route', 'site', 'reaction_notes']);
        });
    }
};
