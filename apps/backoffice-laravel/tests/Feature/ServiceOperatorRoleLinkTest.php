<?php

namespace Tests\Feature;

use App\Models\OperatorRole;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOperatorRoleLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_create_form_shows_operator_role_selector_and_quick_link(): void
    {
        $operatorRole = OperatorRole::create([
            'code' => 'GROOM',
            'name' => 'Groomer',
            'is_active' => true,
        ]);

        $response = $this->get(route('services.create'));

        $response->assertOk();
        $response->assertSee('Tipo de operador');
        $response->assertSee('Nuevo tipo de operador');
        $response->assertSee('Groomer');
        $response->assertSee((string) $operatorRole->id, false);
        $response->assertSee(route('operator-roles.create', ['return_to' => route('services.create')]), false);
    }

    public function test_service_can_be_created_with_operator_role(): void
    {
        $operatorRole = OperatorRole::create([
            'code' => 'BATH',
            'name' => 'Bañado',
            'is_active' => true,
        ]);

        $response = $this->post(route('services.store'), [
            'code' => 'SPA-0099',
            'operator_role_id' => $operatorRole->id,
            'type' => 'spa',
            'name' => 'Baño premium',
            'description' => 'Servicio con secado y perfume.',
            'suggested_price' => '350.00',
            'suggested_duration_minutes' => 60,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('services.index'));

        $this->assertDatabaseHas('services', [
            'code' => 'SPA-0099',
            'name' => 'Baño premium',
            'operator_role_id' => $operatorRole->id,
        ]);

        $service = Service::query()->where('code', 'SPA-0099')->firstOrFail();
        $showResponse = $this->get(route('services.show', $service));

        $showResponse->assertOk();
        $showResponse->assertSee('Tipo de operador');
        $showResponse->assertSee('Bañado');
    }

    public function test_operator_role_create_screen_and_store_can_return_to_service_form(): void
    {
        $returnTo = route('services.create');

        $createResponse = $this->get(route('operator-roles.create', ['return_to' => $returnTo]));

        $createResponse->assertOk();
        $createResponse->assertSee('Regresar');
        $createResponse->assertSee('name="return_to" value="' . e($returnTo) . '"', false);

        $storeResponse = $this->post(route('operator-roles.store'), [
            'code' => 'STYLE',
            'name' => 'Stylist',
            'default_hourly_rate' => '250.00',
            'is_active' => '1',
            'return_to' => $returnTo,
        ]);

        $storeResponse->assertRedirect($returnTo);
        $this->assertDatabaseHas('operator_roles', [
            'code' => 'STYLE',
            'name' => 'Stylist',
        ]);
    }

    public function test_service_edit_loads_even_if_linked_operator_role_is_inactive(): void
    {
        $inactiveOperatorRole = OperatorRole::create([
            'code' => 'HOTEL',
            'name' => 'Hotelero',
            'is_active' => false,
        ]);

        $service = Service::create([
            'code' => 'HOT-0001',
            'operator_role_id' => $inactiveOperatorRole->id,
            'type' => 'hotel',
            'name' => 'Hospedaje base',
            'description' => 'Hospedaje nocturno.',
            'price' => '350.00',
            'suggested_price' => '350.00',
            'duration_minutes' => 1440,
            'suggested_duration_minutes' => 1440,
            'is_active' => true,
        ]);

        $response = $this->get(route('services.edit', $service));

        $response->assertOk();
        $response->assertSee('Hotelero');
    }
}