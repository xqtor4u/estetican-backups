<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZEUS-047 (07/10/2026): catálogo de días inhábiles (festivos, cierres). Una cita de una serie
 * recurrente que cae en uno de estos días se recorre sola al siguiente día y hora disponible.
 * `branch_id` null = aplica a todas las sucursales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_working_days', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reason', 120);
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_working_days');
    }
};
