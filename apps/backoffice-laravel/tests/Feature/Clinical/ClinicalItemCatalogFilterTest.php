<?php

namespace Tests\Feature\Clinical;

use App\Models\Client;
use App\Models\ClinicalPrescriptionItem;
use App\Models\ClinicalVisit;
use App\Models\Item;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClinicalItemCatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function operator(): Operator
    {
        return Operator::create(['code' => 'VET'.uniqid(), 'name' => 'Dra. Vet', 'first_name' => 'Dra. Vet', 'is_active' => true]);
    }

    private function userWithClinicalPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'staff'.uniqid(),
            'first_name' => 'Staff',
            'apellido_paterno' => 'Test',
            'email' => 'staff'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
    }

    public function test_vaccine_item_selector_only_shows_items_with_department_vacunas(): void
    {
        $pet = $this->pet();
        $user = $this->userWithClinicalPermissions(['ver clinico']);

        Item::create(['name' => 'Nobivac Rabia Frasco', 'department' => 'Vacunas', 'is_active' => true]);
        Item::create(['name' => 'Amoxicilina Suspension', 'department' => 'Farmacia', 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('clinical.pets.show', $pet));

        $response->assertOk();
        $response->assertSee('Nobivac Rabia Frasco');
        $response->assertDontSee('Amoxicilina Suspension');
    }

    public function test_prescription_item_selector_only_shows_items_with_department_farmacia(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();
        $visit = ClinicalVisit::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visited_at' => now(),
            'reason_for_visit' => 'Consulta',
        ]);
        $user = $this->userWithClinicalPermissions(['ver clinico']);

        Item::create(['name' => 'Amoxicilina Suspension', 'department' => 'Farmacia', 'is_active' => true]);
        Item::create(['name' => 'Nobivac Rabia Frasco', 'department' => 'Vacunas', 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('clinical.visits.show', $visit));

        $response->assertOk();
        $response->assertSee('Amoxicilina Suspension');
        $response->assertDontSee('Nobivac Rabia Frasco');
    }

    public function test_prescription_store_accepts_optional_item_id_and_links_it(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();
        $visit = ClinicalVisit::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visited_at' => now(),
            'reason_for_visit' => 'Consulta',
        ]);
        $item = Item::create(['name' => 'Amoxicilina Suspension', 'department' => 'Farmacia', 'is_active' => true]);
        $user = $this->userWithClinicalPermissions(['editar clinico']);

        $response = $this->actingAs($user)->post(route('clinical.prescriptions.store', $visit), [
            'items' => [
                [
                    'item_id' => $item->id,
                    'drug_name' => 'Amoxicilina Suspension',
                    'dose' => '5ml',
                    'route' => 'oral',
                    'frequency' => 'cada 12 horas',
                ],
            ],
        ]);

        $response->assertRedirect(route('clinical.visits.show', $visit));
        $prescriptionItem = ClinicalPrescriptionItem::first();
        $this->assertNotNull($prescriptionItem);
        $this->assertSame($item->id, $prescriptionItem->item_id);
    }

    public function test_prescription_store_still_works_without_item_id(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();
        $visit = ClinicalVisit::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visited_at' => now(),
            'reason_for_visit' => 'Consulta',
        ]);
        $user = $this->userWithClinicalPermissions(['editar clinico']);

        $response = $this->actingAs($user)->post(route('clinical.prescriptions.store', $visit), [
            'items' => [
                [
                    'drug_name' => 'Preparado compuesto (no catalogado)',
                    'dose' => '1 tableta',
                    'route' => 'oral',
                    'frequency' => 'cada 24 horas',
                ],
            ],
        ]);

        $response->assertRedirect(route('clinical.visits.show', $visit));
        $prescriptionItem = ClinicalPrescriptionItem::first();
        $this->assertNotNull($prescriptionItem);
        $this->assertNull($prescriptionItem->item_id);
    }

    public function test_prescription_store_rejects_a_nonexistent_item_id(): void
    {
        $pet = $this->pet();
        $operator = $this->operator();
        $visit = ClinicalVisit::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visited_at' => now(),
            'reason_for_visit' => 'Consulta',
        ]);
        $user = $this->userWithClinicalPermissions(['editar clinico']);

        $response = $this->actingAs($user)->post(route('clinical.prescriptions.store', $visit), [
            'items' => [
                [
                    'item_id' => 999999,
                    'drug_name' => 'Amoxicilina',
                    'dose' => '5ml',
                    'route' => 'oral',
                    'frequency' => 'cada 12 horas',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.item_id');
        $this->assertSame(0, ClinicalPrescriptionItem::count());
    }
}
