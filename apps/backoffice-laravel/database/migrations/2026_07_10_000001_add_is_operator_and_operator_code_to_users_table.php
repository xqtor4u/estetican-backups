<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_operator')) {
                $table->boolean('is_operator')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('users', 'operator_code')) {
                $table->string('operator_code')->nullable()->unique()->after('is_operator');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('users', 'operator_code') ? 'operator_code' : null,
                Schema::hasColumn('users', 'is_operator')   ? 'is_operator'   : null,
            ]));
        });
    }
};
