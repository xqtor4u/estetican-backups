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
        Schema::table('executed_services', function (Blueprint $table) {
            $table->foreignId('operator_id')->nullable()->after('pet_id')->constrained('operators')->nullOnDelete();
            $table->text('service_summary')->nullable()->after('final_price');
        });

        Schema::table('executed_service_items', function (Blueprint $table) {
            $table->string('service_name_snapshot')->nullable()->after('service_id');
            $table->text('service_description_snapshot')->nullable()->after('service_name_snapshot');
            $table->string('service_type_snapshot')->nullable()->after('service_description_snapshot');
            $table->integer('duration_minutes_snapshot')->nullable()->after('charged_price');
        });

        $items = DB::table('executed_service_items')
            ->join('services', 'services.id', '=', 'executed_service_items.service_id')
            ->select(
                'executed_service_items.id',
                'services.name',
                'services.type',
                'services.duration_minutes'
            )
            ->get();

        foreach ($items as $item) {
            DB::table('executed_service_items')
                ->where('id', $item->id)
                ->update([
                    'service_name_snapshot' => $item->name,
                    'service_type_snapshot' => $item->type,
                    'duration_minutes_snapshot' => $item->duration_minutes,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('executed_service_items', function (Blueprint $table) {
            $table->dropColumn([
                'service_name_snapshot',
                'service_description_snapshot',
                'service_type_snapshot',
                'duration_minutes_snapshot',
            ]);
        });

        Schema::table('executed_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operator_id');
            $table->dropColumn('service_summary');
        });
    }
};