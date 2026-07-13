<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Todas arrancan en true (opt-out, no opt-in) — decisión explícita del
            // usuario: los clientes con relación comercial existente quedan
            // suscritos por defecto, con opción fácil de darse de baja.
            $table->boolean('receives_offers')->default(true)->after('notes');
            $table->boolean('receives_service_reminders')->default(true)->after('receives_offers');
            $table->boolean('receives_job_updates')->default(true)->after('receives_service_reminders');
            $table->boolean('receives_account_statements')->default(true)->after('receives_job_updates');
            $table->boolean('receives_other_notifications')->default(true)->after('receives_account_statements');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'receives_offers',
                'receives_service_reminders',
                'receives_job_updates',
                'receives_account_statements',
                'receives_other_notifications',
            ]);
        });
    }
};
