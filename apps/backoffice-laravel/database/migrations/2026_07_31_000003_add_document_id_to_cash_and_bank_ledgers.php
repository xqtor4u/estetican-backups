<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_ledgers', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->after('payable_id')->constrained('documents')->nullOnDelete();
        });

        Schema::table('bank_ledgers', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->after('payable_id')->constrained('documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_ledgers', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropColumn('document_id');
        });

        Schema::table('cash_ledgers', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropColumn('document_id');
        });
    }
};
