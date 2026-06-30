<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->foreignId('operator_role_id')
                ->nullable()
                ->after('role')
                ->constrained('operator_roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\OperatorRole::class);
            $table->dropColumn('operator_role_id');
        });
    }
};
