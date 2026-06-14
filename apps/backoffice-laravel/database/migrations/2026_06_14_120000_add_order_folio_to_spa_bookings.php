<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->foreignId('order_series_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('document_series')
                  ->nullOnDelete();

            $table->string('order_folio', 30)
                  ->nullable()
                  ->unique()
                  ->after('order_series_id')
                  ->comment('Número de orden generado de la serie (ej. OT-SPA-0042)');
        });
    }

    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropUnique(['order_folio']);
            $table->dropConstrainedForeignId('order_series_id');
            $table->dropColumn('order_folio');
        });
    }
};
