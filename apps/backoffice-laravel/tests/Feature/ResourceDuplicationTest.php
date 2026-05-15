<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceDuplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_views_expose_duplicate_action(): void
    {
        $branch = Branch::create([
            'code' => 'MTY-CEN',
            'name' => 'Monterrey Centro',
            'is_active' => true,
        ]);

        $resource = Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => 'J-A01',
            'name' => 'Jaula A01',
            'capacity_label' => 'Mediana',
            'administrative_status' => 'active',
            'operational_status' => 'available',
            'notes' => 'Clasificacion base para grooming.',
        ]);

        $indexResponse = $this->get(route('resources.index'));
        $showResponse = $this->get(route('resources.show', $resource));
        $editResponse = $this->get(route('resources.edit', $resource));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Panorama actual');
        $indexResponse->assertSee('Duplicar');
        $indexResponse->assertSee(route('resources.duplicate', $resource), false);
        $indexResponse->assertSee('sort=name', false);
        $indexResponse->assertSee('direction=asc', false);

        $showResponse->assertOk();
        $showResponse->assertSee('Duplicar recurso');
        $showResponse->assertSee(route('resources.duplicate', $resource), false);

        $editResponse->assertOk();
        $editResponse->assertSee('Duplicar recurso');
        $editResponse->assertSee(route('resources.duplicate', $resource), false);
    }

    public function test_resource_can_be_duplicated_preserving_classification_but_requiring_review(): void
    {
        $branch = Branch::create([
            'code' => 'GDL-NTE',
            'name' => 'Guadalajara Norte',
            'is_active' => true,
        ]);

        $resource = Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => 'J-B05',
            'name' => 'Jaula Banio 05',
            'capacity_label' => 'Grande',
            'administrative_status' => 'active',
            'operational_status' => 'available',
            'notes' => 'Misma clasificacion para perros grandes.',
        ]);

        $response = $this->post(route('resources.duplicate', $resource));

        $duplicate = Resource::query()
            ->where('branch_id', $branch->id)
            ->where('code', 'J-B05-COPY')
            ->firstOrFail();

        $response->assertRedirect(route('resources.edit', $duplicate));

        $this->assertSame('Jaula Banio 05 (copia)', $duplicate->name);
        $this->assertSame('cage', $duplicate->resource_type);
        $this->assertSame('Grande', $duplicate->capacity_label);
        $this->assertSame('inactive', $duplicate->administrative_status);
        $this->assertSame('available', $duplicate->operational_status);
        $this->assertSame('Misma clasificacion para perros grandes.', $duplicate->notes);
    }

    public function test_resources_index_supports_blueprint_sorting_by_allocations(): void
    {
        $branch = Branch::create([
            'code' => 'CDMX-SUR',
            'name' => 'CDMX Sur',
            'is_active' => true,
        ]);

        $lightResource = Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => 'J-C01',
            'name' => 'Jaula C01',
            'capacity_label' => 'Chica',
            'administrative_status' => 'active',
            'operational_status' => 'available',
        ]);

        $heavyResource = Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => 'J-C02',
            'name' => 'Jaula C02',
            'capacity_label' => 'Grande',
            'administrative_status' => 'inactive',
            'operational_status' => 'cleaning',
        ]);

        $heavyResource->allocations()->create([
            'allocation_type' => 'maintenance',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);

        $heavyResource->allocations()->create([
            'allocation_type' => 'manual_block',
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(4),
        ]);

        $response = $this->get(route('resources.index', [
            'sort' => 'allocations',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $response->assertSeeInOrder(['Jaula C02', 'Jaula C01']);
        $response->assertSee('Carga visible');
        $response->assertSee('bloqueos y usos');
        $this->assertNotNull($lightResource);
    }
}