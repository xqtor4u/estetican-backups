<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name'))
                $table->string('first_name')->nullable()->after('name');
            if (!Schema::hasColumn('users', 'last_name'))
                $table->string('last_name')->nullable()->after('first_name');
            if (!Schema::hasColumn('users', 'ine_number'))
                $table->string('ine_number')->nullable()->after('last_name');
            if (!Schema::hasColumn('users', 'imss_number'))
                $table->string('imss_number')->nullable()->after('ine_number');
            if (!Schema::hasColumn('users', 'address'))
                $table->text('address')->nullable()->after('imss_number');
            if (!Schema::hasColumn('users', 'phone'))
                $table->string('phone')->nullable()->after('address');
            if (!Schema::hasColumn('users', 'profile_photo_path'))
                $table->string('profile_photo_path')->nullable()->after('phone');
            if (!Schema::hasColumn('users', 'emergency_contact_name'))
                $table->string('emergency_contact_name')->nullable()->after('profile_photo_path');
            if (!Schema::hasColumn('users', 'emergency_contact_phone'))
                $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            if (!Schema::hasColumn('users', 'hire_date'))
                $table->date('hire_date')->nullable()->after('emergency_contact_phone');
            if (!Schema::hasColumn('users', 'role'))
                $table->string('role')->nullable()->after('hire_date');
            if (!Schema::hasColumn('users', 'is_active'))
                $table->boolean('is_active')->default(true)->after('role');
            if (!Schema::hasColumn('users', 'notes'))
                $table->text('notes')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('users', 'first_name')          ? 'first_name'          : null,
                Schema::hasColumn('users', 'last_name')           ? 'last_name'           : null,
                Schema::hasColumn('users', 'ine_number')          ? 'ine_number'          : null,
                Schema::hasColumn('users', 'imss_number')         ? 'imss_number'         : null,
                Schema::hasColumn('users', 'address')             ? 'address'             : null,
                Schema::hasColumn('users', 'phone')               ? 'phone'               : null,
                Schema::hasColumn('users', 'profile_photo_path')  ? 'profile_photo_path'  : null,
                Schema::hasColumn('users', 'emergency_contact_name')  ? 'emergency_contact_name'  : null,
                Schema::hasColumn('users', 'emergency_contact_phone') ? 'emergency_contact_phone' : null,
                Schema::hasColumn('users', 'hire_date')           ? 'hire_date'           : null,
                Schema::hasColumn('users', 'role')                ? 'role'                : null,
                Schema::hasColumn('users', 'is_active')           ? 'is_active'           : null,
                Schema::hasColumn('users', 'notes')               ? 'notes'               : null,
            ]));
        });
    }
};
