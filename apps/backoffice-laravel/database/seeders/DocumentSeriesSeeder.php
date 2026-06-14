<?php

namespace Database\Seeders;

use App\Models\DocumentSeries;
use Illuminate\Database\Seeder;

class DocumentSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $series = [
            [
                'document_type' => 'recibo',
                'name'          => 'Recibos de pago',
                'prefix'        => 'R-',
                'suffix'        => '',
                'next_number'   => 1,
                'padding'       => 4,
                'branch_id'     => null,
                'is_active'     => true,
            ],
            [
                'document_type' => 'factura',
                'name'          => 'Facturas',
                'prefix'        => 'F-',
                'suffix'        => '',
                'next_number'   => 1,
                'padding'       => 4,
                'branch_id'     => null,
                'is_active'     => true,
            ],
            [
                'document_type' => 'sin_documento',
                'name'          => 'Sin documento',
                'prefix'        => 'SD-',
                'suffix'        => '',
                'next_number'   => 1,
                'padding'       => 4,
                'branch_id'     => null,
                'is_active'     => true,
            ],
        ];

        foreach ($series as $data) {
            DocumentSeries::firstOrCreate(
                ['document_type' => $data['document_type'], 'branch_id' => null],
                $data
            );
        }
    }
}
