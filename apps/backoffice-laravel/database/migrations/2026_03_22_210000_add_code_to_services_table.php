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
        Schema::table('services', function (Blueprint $table) {
            $table->string('code')->nullable()->after('type');
        });

        $services = DB::table('services')
            ->select('id', 'type')
            ->orderBy('id')
            ->get();

        $counters = [];

        foreach ($services as $service) {
            $prefix = match ($service->type) {
                'spa' => 'SPA',
                'hotel' => 'HOT',
                'extra' => 'EXT',
                'combo' => 'COM',
                default => 'SRV',
            };

            $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;

            DB::table('services')
                ->where('id', $service->id)
                ->update([
                    'code' => sprintf('%s-%04d', $prefix, $counters[$prefix]),
                ]);
        }

        Schema::table('services', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};