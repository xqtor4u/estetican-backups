<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class MasterAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Asegura que la tabla exista
        if (!Schema::hasTable('users')) return;

        // Crea o actualiza el usuario admin (no se puede borrar ni editar por otros)
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@localhost'],
            [
                'name' => 'Admin',
                'email' => 'admin@localhost',
                'password' => Hash::make('admin'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
