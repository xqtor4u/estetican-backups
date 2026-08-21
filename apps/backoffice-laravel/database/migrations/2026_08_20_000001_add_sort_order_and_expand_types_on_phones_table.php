<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('phones', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('type');
        });

        // Backfill: dentro de cada cliente, preserva el orden de captura existente (por id)
        // como orden de importancia inicial — antes de esto no existía ningún orden real.
        DB::table('phones')
            ->select('id', 'client_id')
            ->orderBy('client_id')
            ->orderBy('id')
            ->get()
            ->groupBy('client_id')
            ->each(function ($phones) {
                foreach ($phones->values() as $index => $phone) {
                    DB::table('phones')->where('id', $phone->id)->update(['sort_order' => $index]);
                }
            });

        // El vocabulario de tipos pasa de mobile/fixed a mobile/home/work/other — 'fixed' (Fijo)
        // se reclasifica como 'home' (Casa), el uso más común de un teléfono fijo capturado hasta
        // ahora. No es perfectamente reversible (no se puede distinguir después cuáles 'home'
        // venían de 'fixed' vs. capturados directo como 'home'), mismo tipo de transformación
        // ya aplicada en otras migraciones de esta tabla (ver 2026_03_20_000002).
        DB::table('phones')->where('type', 'fixed')->update(['type' => 'home']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phones', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
