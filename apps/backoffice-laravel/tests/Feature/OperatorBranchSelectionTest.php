<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Operator;
use App\Models\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorBranchSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_form_uses_branch_selector_from_catalog(): void
    {
        $branch = Branch::create([
            'code' => 'MTY-CEN',
            'name' => 'Monterrey Centro',
            'is_active' => true,
        ]);

        $response = $this->get(route('operators.create'));

        $response->assertOk();
        $response->assertSee('Base de operación');
        $response->assertSee('Nueva sucursal');
        $response->assertSee('Monterrey Centro');
        $response->assertDontSee('name="base_branch_name"', false);
        $response->assertSee('name="branch_id"', false);
        $response->assertSee((string) $branch->id, false);
    }

    public function test_operator_can_be_created_with_catalog_branch_assignment(): void
    {
        $branch = Branch::create([
            'code' => 'GDL-NTE',
            'name' => 'Guadalajara Norte',
            'is_active' => true,
        ]);

        $role = OperatorRole::create([
            'code' => 'GROOM',
            'name' => 'Groomer',
            'is_active' => true,
        ]);

        $response = $this->post(route('operators.store'), [
            'code' => 'OP-001',
            'first_name' => 'Laura',
            'apellido_paterno' => 'Campos',
            'role_ids' => [$role->id],
            'branch_id' => $branch->id,
            'hourly_rate' => '180.00',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('operators.index'));

        $operator = Operator::query()->where('code', 'OP-001')->firstOrFail();

        $this->assertDatabaseHas('operator_branch_assignments', [
            'operator_id' => $operator->id,
            'branch_id' => $branch->id,
            'is_primary' => true,
        ]);
    }

    public function test_branch_create_screen_can_return_to_operator_form_with_selected_branch(): void
    {
        $returnTo = route('operators.create');

        $createResponse = $this->get(route('branches.create', ['return_to' => $returnTo]));

        $createResponse->assertOk();
        $createResponse->assertSee('Regresar');
        $createResponse->assertSee('Intentar traer coordenadas');
        $createResponse->assertSee('Mostrar en Google Maps');
        $createResponse->assertSee('Importar punto exacto');
        $createResponse->assertSee('Copia y pega aquí las coordenadas, por ejemplo 25.6866142,-100.3161126');
        $createResponse->assertSee('Usa calle, número exterior e interior por separado.', false);
        $createResponse->assertSee('Número exterior');
        $createResponse->assertSee('Interior');
        $createResponse->assertSee('address-editor-geocode-btn', false);
        $createResponse->assertSee('address-editor-maps-btn', false);
        $createResponse->assertSee('address-editor-import-btn', false);
        $createResponse->assertSee('name="return_to" value="' . e($returnTo) . '"', false);

        $storeResponse = $this->post(route('branches.store'), [
            'code' => 'CDMX-SUR',
            'name' => 'CDMX Sur',
            'street' => 'Av. Patriotismo',
            'exterior_number' => '123',
            'interior_number' => 'II',
            'colonia' => 'San Pedro de los Pinos',
            'city' => 'Benito Juárez',
            'state' => 'CDMX',
            'zip' => '03800',
            'country' => 'México',
            'is_active' => '1',
            'return_to' => $returnTo,
        ]);

        $branch = Branch::query()->where('code', 'CDMX-SUR')->firstOrFail();

        $storeResponse->assertRedirect($returnTo . '?branch_id=' . $branch->id);

        $returnResponse = $this->get($returnTo . '?branch_id=' . $branch->id);
        $returnResponse->assertOk();
        $returnResponse->assertSee('CDMX Sur');
        $returnResponse->assertSee('value="' . $branch->id . '" selected', false);
    }

    public function test_branch_show_exposes_atomized_address_and_share_links(): void
    {
        $branch = Branch::create([
            'code' => 'MTY-SUR',
            'name' => 'Monterrey Sur',
            'street' => 'Av. Revolución',
            'exterior_number' => '2450',
            'interior_number' => 'II',
            'colonia' => 'Ladrillera',
            'city' => 'Monterrey',
            'state' => 'Nuevo León',
            'zip' => '64830',
            'country' => 'México',
            'lat' => 25.65123456,
            'lng' => -100.28987654,
            'is_active' => true,
        ]);

        $response = $this->get(route('branches.show', $branch));

        $response->assertOk();
        $response->assertSee('Av. Revolución 2450 Int II');
        $response->assertSee('Ladrillera');
        $response->assertSee('Monterrey');
        $response->assertSee('Nuevo León');
        $response->assertSee('64830');
        $response->assertSee('Abrir en Maps');
        $response->assertSee('Compartir por WhatsApp');
        $response->assertSee('https://www.google.com/maps?q=25.65123456,-100.28987654', false);
        $response->assertSee('https://wa.me/?text=', false);
    }

    public function test_branch_can_be_deleted_when_it_has_no_assignments(): void
    {
        $branch = Branch::create([
            'code' => 'TMP-001',
            'name' => 'Temporal',
            'is_active' => true,
        ]);

        $response = $this->delete(route('branches.destroy', $branch));

        $response->assertRedirect(route('branches.index'));
        $this->assertDatabaseMissing('branches', [
            'id' => $branch->id,
        ]);
    }

    public function test_branch_cannot_be_deleted_when_it_has_operator_assignments(): void
    {
        $branch = Branch::create([
            'code' => 'GDL-SUR',
            'name' => 'Guadalajara Sur',
            'is_active' => true,
        ]);

        $role = OperatorRole::create([
            'code' => 'HOTEL',
            'name' => 'Hotelero',
            'is_active' => true,
        ]);

        $operator = Operator::create([
            'code' => 'OP-900',
            'first_name' => 'Mario',
            'apellido_paterno' => 'León',
            'name' => 'Mario León',
            'role' => 'Hotelero',
            'is_active' => true,
        ]);

        $operator->roleAssignments()->create([
            'operator_role_id' => $role->id,
            'is_primary' => true,
            'starts_at' => now(),
        ]);

        $operator->branchAssignments()->create([
            'branch_id' => $branch->id,
            'is_primary' => true,
            'starts_at' => now(),
        ]);

        $response = $this->delete(route('branches.destroy', $branch));

        $response->assertRedirect(route('branches.index'));
        $response->assertSessionHas('error', 'No se puede eliminar la sucursal porque todavía tiene operadores asignados.');
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
        ]);
    }
}