<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * full_name quedó NOT NULL desde 2026_03_22_231000 (enrich_operators_personal_data).
     * BL-045b lo vuelve un campo vestigial (accessor calculado desde first_name +
     * apellido_paterno + apellido_materno) — ya no se escribe directo en create(), así
     * que la restricción NOT NULL rompía cualquier alta nueva de operador.
     */
    public function up(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->string('full_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->string('full_name')->nullable(false)->change();
        });
    }
};
