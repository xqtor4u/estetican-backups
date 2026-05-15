<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['last_name', 'first_name'], 'clients_last_name_first_name_idx');
            $table->index(['first_name', 'last_name'], 'clients_first_name_last_name_idx');
            $table->index('email', 'clients_email_idx');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->index(['client_id', 'death_date', 'name'], 'pets_client_death_name_idx');
            $table->index(['death_date', 'name'], 'pets_death_name_idx');
            $table->index(['species', 'name'], 'pets_species_name_idx');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index(['is_active', 'type', 'name'], 'services_active_type_name_idx');
        });

        Schema::table('operator_roles', function (Blueprint $table) {
            $table->index(['is_active', 'name'], 'operator_roles_active_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('operator_roles', function (Blueprint $table) {
            $table->dropIndex('operator_roles_active_name_idx');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('services_active_type_name_idx');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->dropIndex('pets_species_name_idx');
            $table->dropIndex('pets_death_name_idx');
            $table->dropIndex('pets_client_death_name_idx');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_email_idx');
            $table->dropIndex('clients_first_name_last_name_idx');
            $table->dropIndex('clients_last_name_first_name_idx');
        });
    }
};