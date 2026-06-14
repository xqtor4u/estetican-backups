<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ── ACTIVOS ──────────────────────────────────────────────
            ['code' => '1000', 'name' => 'Activos',                       'type' => 'activo',  'allows_entries' => false, 'parent' => null],
            ['code' => '1100', 'name' => 'Caja',                          'type' => 'activo',  'allows_entries' => true,  'parent' => '1000'],
            ['code' => '1200', 'name' => 'Bancos',                        'type' => 'activo',  'allows_entries' => false, 'parent' => '1000'],
            ['code' => '1210', 'name' => 'Cuenta bancaria principal',      'type' => 'activo',  'allows_entries' => true,  'parent' => '1200'],
            ['code' => '1300', 'name' => 'Cuentas por cobrar',            'type' => 'activo',  'allows_entries' => false, 'parent' => '1000'],
            ['code' => '1310', 'name' => 'Clientes',                      'type' => 'activo',  'allows_entries' => true,  'parent' => '1300'],

            // ── PASIVOS ──────────────────────────────────────────────
            ['code' => '2000', 'name' => 'Pasivos',                       'type' => 'pasivo',  'allows_entries' => false, 'parent' => null],
            ['code' => '2100', 'name' => 'Cuentas por pagar',             'type' => 'pasivo',  'allows_entries' => true,  'parent' => '2000'],
            ['code' => '2200', 'name' => 'Impuestos por pagar',           'type' => 'pasivo',  'allows_entries' => true,  'parent' => '2000'],

            // ── CAPITAL ──────────────────────────────────────────────
            ['code' => '3000', 'name' => 'Capital',                       'type' => 'capital', 'allows_entries' => false, 'parent' => null],
            ['code' => '3100', 'name' => 'Capital del negocio',           'type' => 'capital', 'allows_entries' => true,  'parent' => '3000'],
            ['code' => '3200', 'name' => 'Utilidades del ejercicio',      'type' => 'capital', 'allows_entries' => true,  'parent' => '3000'],

            // ── INGRESOS ─────────────────────────────────────────────
            ['code' => '4000', 'name' => 'Ingresos',                      'type' => 'ingreso', 'allows_entries' => false, 'parent' => null],
            ['code' => '4100', 'name' => 'Ingresos — Grooming / SPA',     'type' => 'ingreso', 'allows_entries' => true,  'parent' => '4000'],
            ['code' => '4200', 'name' => 'Ingresos — Veterinaria',        'type' => 'ingreso', 'allows_entries' => true,  'parent' => '4000'],
            ['code' => '4300', 'name' => 'Ingresos — Medicamentos',       'type' => 'ingreso', 'allows_entries' => true,  'parent' => '4000'],
            ['code' => '4400', 'name' => 'Ingresos — Accesorios / Tienda','type' => 'ingreso', 'allows_entries' => true,  'parent' => '4000'],
            ['code' => '4500', 'name' => 'Ingresos — Hospedaje / Hotel',  'type' => 'ingreso', 'allows_entries' => true,  'parent' => '4000'],
            ['code' => '4900', 'name' => 'Otros ingresos',                'type' => 'ingreso', 'allows_entries' => true,  'parent' => '4000'],

            // ── GASTOS ───────────────────────────────────────────────
            ['code' => '5000', 'name' => 'Gastos',                        'type' => 'gasto',   'allows_entries' => false, 'parent' => null],
            ['code' => '5100', 'name' => 'Nómina y sueldos',              'type' => 'gasto',   'allows_entries' => true,  'parent' => '5000'],
            ['code' => '5200', 'name' => 'Insumos y materiales',          'type' => 'gasto',   'allows_entries' => true,  'parent' => '5000'],
            ['code' => '5300', 'name' => 'Renta y servicios',             'type' => 'gasto',   'allows_entries' => true,  'parent' => '5000'],
            ['code' => '5900', 'name' => 'Gastos generales',              'type' => 'gasto',   'allows_entries' => true,  'parent' => '5000'],
        ];

        // Insertar en dos pasadas: primero las cuentas raíz, luego las hijas
        $ids = [];

        foreach ($accounts as $data) {
            if ($data['parent'] !== null) {
                continue;
            }

            $account = Account::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name'           => $data['name'],
                    'type'           => $data['type'],
                    'allows_entries' => $data['allows_entries'],
                    'is_active'      => true,
                ]
            );
            $ids[$data['code']] = $account->id;
        }

        foreach ($accounts as $data) {
            if ($data['parent'] === null) {
                continue;
            }

            $account = Account::firstOrCreate(
                ['code' => $data['code']],
                [
                    'parent_id'      => $ids[$data['parent']],
                    'name'           => $data['name'],
                    'type'           => $data['type'],
                    'allows_entries' => $data['allows_entries'],
                    'is_active'      => true,
                ]
            );
            $ids[$data['code']] = $account->id;
        }
    }
}
