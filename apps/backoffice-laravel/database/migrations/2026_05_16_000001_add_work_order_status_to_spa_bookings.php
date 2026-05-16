<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE spa_bookings MODIFY COLUMN status ENUM('scheduled','work_order','completed','cancelled','no_show','unfulfillable') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE spa_bookings MODIFY COLUMN status ENUM('scheduled','completed','cancelled','no_show','unfulfillable') NOT NULL DEFAULT 'scheduled'");
    }
};
