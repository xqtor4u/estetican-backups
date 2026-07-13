<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['booking_messages', 'recurrence_messages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('channel')->default('whatsapp')->after('whatsapp_template_id');
                $blueprint->string('email_address')->nullable()->after('phone_number');
            });

            // phone_number/wa_link solo aplican al canal whatsapp — dejan de ser
            // obligatorias para poder registrar envíos por correo. No se usa
            // ->nullable()->change() porque el proyecto no tiene doctrine/dbal.
            DB::statement("ALTER TABLE `{$table}` MODIFY `phone_number` VARCHAR(255) NULL");
            DB::statement("ALTER TABLE `{$table}` MODIFY `wa_link` TEXT NULL");
        }
    }

    public function down(): void
    {
        foreach (['booking_messages', 'recurrence_messages'] as $table) {
            DB::statement("UPDATE `{$table}` SET `phone_number` = '' WHERE `phone_number` IS NULL");
            DB::statement("UPDATE `{$table}` SET `wa_link` = '' WHERE `wa_link` IS NULL");
            DB::statement("ALTER TABLE `{$table}` MODIFY `phone_number` VARCHAR(255) NOT NULL");
            DB::statement("ALTER TABLE `{$table}` MODIFY `wa_link` TEXT NOT NULL");

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['channel', 'email_address']);
            });
        }
    }
};
