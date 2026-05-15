<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('ine_number')->nullable()->after('full_name');
            $table->string('imss_number')->nullable()->after('ine_number');
            $table->text('address')->nullable()->after('imss_number');
            $table->string('phone')->nullable()->after('address');
            $table->string('profile_photo_path')->nullable()->after('phone');
            $table->string('emergency_contact_name')->nullable()->after('profile_photo_path');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->date('hire_date')->nullable()->after('emergency_contact_phone');
            $table->string('role')->nullable()->after('hire_date');
            $table->boolean('is_active')->default(true)->after('role');
            $table->text('notes')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'ine_number',
                'imss_number',
                'address',
                'phone',
                'profile_photo_path',
                'emergency_contact_name',
                'emergency_contact_phone',
                'hire_date',
                'role',
                'is_active',
                'notes',
            ]);
        });
    }
};
