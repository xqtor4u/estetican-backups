<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $caja  = Account::where('code', '1100')->first()?->id;
        $banco = Account::where('code', '1210')->first()?->id;

        $methods = [
            [
                'code'               => 'EFECT',
                'name'               => 'Efectivo',
                'type'               => 'cash',
                'account_id'         => $caja,
                'requires_reference' => false,
                'is_active'          => true,
            ],
            [
                'code'               => 'TARJ_DEB',
                'name'               => 'Tarjeta de débito',
                'type'               => 'card',
                'account_id'         => $banco,
                'requires_reference' => true,
                'is_active'          => true,
            ],
            [
                'code'               => 'TARJ_CRED',
                'name'               => 'Tarjeta de crédito',
                'type'               => 'card',
                'account_id'         => $banco,
                'requires_reference' => true,
                'is_active'          => true,
            ],
            [
                'code'               => 'SPEI',
                'name'               => 'Transferencia SPEI',
                'type'               => 'transfer',
                'account_id'         => $banco,
                'requires_reference' => true,
                'is_active'          => true,
            ],
            [
                'code'               => 'CRYPTO',
                'name'               => 'Criptomoneda',
                'type'               => 'crypto',
                'account_id'         => null, // pendiente definir cuenta
                'requires_reference' => true,
                'is_active'          => false, // inactivo hasta configurar
            ],
        ];

        foreach ($methods as $data) {
            PaymentMethod::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
