<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_weekly_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=domingo..6=sábado, alineado a Carbon::SUNDAY..SATURDAY
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['operator_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_weekly_schedules');
    }
};
