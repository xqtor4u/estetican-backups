<?php

namespace Tests\Feature\Clinical;

use App\Models\Client;
use App\Models\Item;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::firstOrCreate(['name' => 'alergias.administrar', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'crear catalogo_articulos', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-item-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
        $user->givePermissionTo(['alergias.administrar', 'crear catalogo_articulos']);

        return $user;
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function vaccineService(): Service
    {
        return Service::create([
            'code' => 'VAC-'.uniqid(), 'type' => 'vaccine', 'name' => 'Vacuna Rabia',
            'price' => 0, 'duration_minutes' => 10, 'is_active' => true,
        ]);
    }

    public function test_creating_an_item_does_not_require_stock_fields(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);

        $response = $this->actingAs($this->admin())->post(route('items.store'), [
            'name' => 'Vacuna Antirrábica',
            'brand' => 'Nobivac',
            'presentation' => 'Frasco 1 dosis',
            'department' => 'Farmacia',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'name' => 'Vacuna Antirrábica',
            'brand' => 'Nobivac',
            'is_active' => true,
        ]);
    }

    public function test_registering_a_vaccination_with_an_item_auto_fills_manufacturer_from_the_item_brand(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        $pet = $this->pet();
        $service = $this->vaccineService();
        $item = Item::create(['name' => 'Vacuna Antirrábica', 'brand' => 'Nobivac']);

        $response = $this->actingAs($this->admin())->post(route('clinical.vaccinations.store', $pet), [
            'service_id' => $service->id,
            'item_id' => $item->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pet_vaccinations', [
            'pet_id' => $pet->id,
            'item_id' => $item->id,
            'manufacturer' => 'Nobivac',
        ]);
    }

    public function test_registering_an_external_vaccination_does_not_require_an_item(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        $pet = $this->pet();
        $service = $this->vaccineService();

        $response = $this->actingAs($this->admin())->post(route('clinical.vaccinations.store', $pet), [
            'service_id' => $service->id,
            'is_external' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pet_vaccinations', [
            'pet_id' => $pet->id,
            'item_id' => null,
            'is_external' => true,
        ]);
    }

    public function test_applying_a_vaccination_with_an_item_consumes_one_unit_of_stock(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        $pet = $this->pet();
        $service = $this->vaccineService();
        $item = Item::create(['name' => 'Vacuna Antirrábica', 'brand' => 'Nobivac']);
        app(\App\Domain\Inventory\Contracts\ItemMovementServiceInterface::class)
            ->record(itemId: $item->id, type: 'entrada', quantity: 5);

        $this->actingAs($this->admin())->post(route('clinical.vaccinations.store', $pet), [
            'service_id' => $service->id,
            'item_id' => $item->id,
        ]);

        $this->assertSame(4, $item->fresh()->stock_quantity);
        $vaccination = \App\Models\PetVaccination::where('pet_id', $pet->id)->firstOrFail();
        $this->assertDatabaseHas('item_movements', [
            'item_id' => $item->id,
            'type' => 'consumo_servicio',
            'quantity' => -1,
            'reference_type' => \App\Models\PetVaccination::class,
            'reference_id' => $vaccination->id,
        ]);
    }

    public function test_applying_an_external_vaccination_with_an_item_does_not_consume_stock(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        $pet = $this->pet();
        $service = $this->vaccineService();
        $item = Item::create(['name' => 'Vacuna Antirrábica', 'brand' => 'Nobivac']);
        app(\App\Domain\Inventory\Contracts\ItemMovementServiceInterface::class)
            ->record(itemId: $item->id, type: 'entrada', quantity: 5);

        $this->actingAs($this->admin())->post(route('clinical.vaccinations.store', $pet), [
            'service_id' => $service->id,
            'item_id' => $item->id,
            'is_external' => '1',
        ]);

        $this->assertSame(5, $item->fresh()->stock_quantity);
        $this->assertDatabaseMissing('item_movements', ['type' => 'consumo_servicio']);
    }
}
