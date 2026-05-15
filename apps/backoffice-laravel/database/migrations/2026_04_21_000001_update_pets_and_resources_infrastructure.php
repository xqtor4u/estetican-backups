<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update pets table
        Schema::table('pets', function (Blueprint $table) {
            if (!Schema::hasColumn('pets', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('client_id');
            }
            if (!Schema::hasColumn('pets', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 2. Update resources table
        Schema::table('resources', function (Blueprint $table) {
            if (!Schema::hasColumn('resources', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('id');
            }
            if (!Schema::hasColumn('resources', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 3. Data Migration: Sync primary photos to the new columns
        
        // Sync Pets
        $petPhotos = DB::table('pet_photos')
            ->where('is_primary', true)
            ->whereNotNull('photo_url')
            ->get();

        foreach ($petPhotos as $photo) {
            DB::table('pets')
                ->where('id', $photo->pet_id)
                ->whereNull('profile_photo_path')
                ->update(['profile_photo_path' => $photo->photo_url]);
        }

        // Sync Resources
        $resourcePhotos = DB::table('resource_photos')
            ->where('is_primary', true)
            ->whereNotNull('photo_url')
            ->get();

        foreach ($resourcePhotos as $photo) {
            DB::table('resources')
                ->where('id', $photo->resource_id)
                ->whereNull('profile_photo_path')
                ->update(['profile_photo_path' => $photo->photo_url]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['profile_photo_path', 'deleted_at']);
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['profile_photo_path', 'deleted_at']);
        });
    }
};
