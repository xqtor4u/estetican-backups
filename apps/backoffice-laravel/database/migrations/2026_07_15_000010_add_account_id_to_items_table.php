<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mismo patrón que `services.account_id` — necesario para que AccountingService pueda
     * clasificar contablemente el ingreso por artículos (antes solo servicios tenían cuenta).
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('presentation')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
