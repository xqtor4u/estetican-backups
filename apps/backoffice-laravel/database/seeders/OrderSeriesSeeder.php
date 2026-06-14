<?php

namespace Database\Seeders;

use App\Models\DocumentSeries;
use Illuminate\Database\Seeder;

class OrderSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $series = [
            [
                'document_type' => 'orden_spa',
                'name'          => 'Órdenes de servicio SPA',
                'prefix'        => 'OT-SPA-',
                'suffix'        => '',
                'next_number'   => 1,
                'padding'       => 4,
                'branch_id'     => null,
                'is_active'     => true,
            ],
            [
                'document_type' => 'orden_hotel',
                'name'          => 'Órdenes de estancia Hotel',
                'prefix'        => 'OT-HOT-',
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
