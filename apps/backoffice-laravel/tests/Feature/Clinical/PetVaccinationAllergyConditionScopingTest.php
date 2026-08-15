<?php

namespace Tests\Feature\Clinical;

use App\Models\Client;
use App\Models\Pet;
use App\Models\PetAllergy;
use App\Models\PetCondition;
use App\Models\PetVaccination;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * PetVaccinationController/PetAllergyController/PetConditionController resolvían
 * {vaccination}/{allergy}/{condition} por ID suelto, sin confirmar que pertenecieran
 * al {pet} de la URL — cualquier combinación de pet+id que no correspondieran entre sí
 * igual editaba/borraba el registro de OTRA mascota (IDOR real sobre dato clínico).
 * Fix: Route::scopeBindings() en routes/web.php, mismo patrón que resources.events.*.
 */
class PetVaccinationAllergyConditionScopingTest extends TestCase
{
    use RefreshDatabase;

    private function pet(string $name = 'Luka'): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);

        return Pet::create(['client_id' => $client->id, 'name' => $name]);
    }

    private function staffUser(): User
    {
        $user = User::create([
            'name' => 'staff'.uniqid(),
            'first_name' => 'Staff',
            'apellido_paterno' => 'Test',
            'email' => 'staff'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        Permission::firstOrCreate(['name' => 'alergias.administrar', 'guard_name' => 'web']);
        $user->givePermissionTo('alergias.administrar');

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
    }

    public function test_cannot_update_a_vaccination_belonging_to_a_different_pet(): void
    {
        $pet = $this->pet();
        $otherPet = $this->pet('Firulais');
        $user = $this->staffUser();

        $vaccination = $otherPet->vaccinations()->create(['vaccine_name' => 'Rabia']);

        $response = $this->actingAs($user)->put(route('clinical.vaccinations.update', [$pet, $vaccination]), [
            'service_id' => \App\Models\Service::create(['code' => 'VAC'.uniqid(), 'name' => 'Vacuna', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 15])->id,
        ]);

        $response->assertNotFound();
        $this->assertSame('Rabia', $vaccination->fresh()->vaccine_name);
    }

    public function test_cannot_destroy_a_vaccination_belonging_to_a_different_pet(): void
    {
        $pet = $this->pet();
        $otherPet = $this->pet('Firulais');
        $user = $this->staffUser();

        $vaccination = $otherPet->vaccinations()->create(['vaccine_name' => 'Rabia']);

        $response = $this->actingAs($user)->delete(route('clinical.vaccinations.destroy', [$pet, $vaccination]));

        $response->assertNotFound();
        $this->assertSame(1, PetVaccination::count());
    }

    public function test_cannot_update_an_allergy_belonging_to_a_different_pet(): void
    {
        $pet = $this->pet();
        $otherPet = $this->pet('Firulais');
        $user = $this->staffUser();

        $allergy = $otherPet->allergies()->create(['allergen' => 'Pollo']);

        $response = $this->actingAs($user)->put(route('clinical.allergies.update', [$pet, $allergy]), [
            'allergen' => 'Pescado',
            'allergen_type' => 'food',
            'severity' => 'mild',
        ]);

        $response->assertNotFound();
        $this->assertSame('Pollo', $allergy->fresh()->allergen);
    }

    public function test_cannot_destroy_an_allergy_belonging_to_a_different_pet(): void
    {
        $pet = $this->pet();
        $otherPet = $this->pet('Firulais');
        $user = $this->staffUser();

        $allergy = $otherPet->allergies()->create(['allergen' => 'Pollo']);

        $response = $this->actingAs($user)->delete(route('clinical.allergies.destroy', [$pet, $allergy]));

        $response->assertNotFound();
        $this->assertSame(1, PetAllergy::count());
    }

    public function test_cannot_update_a_condition_belonging_to_a_different_pet(): void
    {
        $pet = $this->pet();
        $otherPet = $this->pet('Firulais');
        $user = $this->staffUser();

        $condition = $otherPet->conditions()->create(['name' => 'Displasia de cadera']);

        $response = $this->actingAs($user)->put(route('clinical.conditions.update', [$pet, $condition]), [
            'name' => 'Otro nombre',
            'status' => 'active',
        ]);

        $response->assertNotFound();
        $this->assertSame('Displasia de cadera', $condition->fresh()->name);
    }

    public function test_cannot_destroy_a_condition_belonging_to_a_different_pet(): void
    {
        $pet = $this->pet();
        $otherPet = $this->pet('Firulais');
        $user = $this->staffUser();

        $condition = $otherPet->conditions()->create(['name' => 'Displasia de cadera']);

        $response = $this->actingAs($user)->delete(route('clinical.conditions.destroy', [$pet, $condition]));

        $response->assertNotFound();
        $this->assertSame(1, PetCondition::count());
    }

    public function test_can_still_update_and_destroy_when_the_ids_actually_match(): void
    {
        $pet = $this->pet();
        $user = $this->staffUser();

        $vaccination = $pet->vaccinations()->create(['vaccine_name' => 'Rabia']);
        $allergy = $pet->allergies()->create(['allergen' => 'Pollo']);
        $condition = $pet->conditions()->create(['name' => 'Displasia de cadera']);

        $this->actingAs($user)->delete(route('clinical.vaccinations.destroy', [$pet, $vaccination]))->assertRedirect();
        $this->actingAs($user)->delete(route('clinical.allergies.destroy', [$pet, $allergy]))->assertRedirect();
        $this->actingAs($user)->delete(route('clinical.conditions.destroy', [$pet, $condition]))->assertRedirect();

        $this->assertSame(0, PetVaccination::count());
        $this->assertSame(0, PetAllergy::count());
        $this->assertSame(0, PetCondition::count());
    }
}
