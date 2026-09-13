<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\OperatorRole;
use App\Models\OperatorRoleServiceTemplate;
use App\Models\Pet;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-101 (Fase 3 de SYNC-073): el `<select>` de operador por servicio en el alta de cita web
 * (`agenda/create.blade.php`) ya no ofrece todos los operadores activos — solo los que de verdad
 * pueden hacer ese servicio (`OperatorServiceResolver::operatorsFor`). Antes el rechazo solo se
 * descubría al guardar (guard de `SYNC-099`).
 */
class OperatorEligibilitySelectorTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin()
    {
        return $this->createAdminUser();
    }

    public function test_create_form_only_offers_operators_qualified_for_each_service(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        $role = OperatorRole::create(['code' => 'vet'.uniqid(), 'name' => 'Veterinario '.uniqid()]);
        $consulta = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Consulta veterinaria', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true]);
        OperatorRoleServiceTemplate::create(['operator_role_id' => $role->id, 'service_id' => $consulta->id]);

        $vet = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Dra Calificada', 'first_name' => 'Dra', 'is_active' => true]);
        $vet->roles()->attach($role->id, ['is_primary' => true, 'starts_at' => now()]);
        Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Sin Rol', 'first_name' => 'Sin', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->get(route('pets.bookings.create', $pet));

        $response->assertOk();
        $response->assertSee('Dra Calificada');
        $response->assertDontSee('Sin Rol');
    }

    public function test_create_form_offers_everyone_for_a_service_open_to_all_operators(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño Premium', 'type' => 'spa', 'price' => 300, 'duration_minutes' => 30, 'is_active' => true, 'open_to_all_operators' => true]);
        $anyone = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Cualquiera', 'first_name' => 'Cualquiera', 'is_active' => true]);

        $response = $this->actingAs($this->admin())->get(route('pets.bookings.create', $pet));

        $response->assertOk();
        $response->assertSee('Cualquiera');
    }
}
