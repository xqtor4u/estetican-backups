<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAddressHarmonizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_create_form_exposes_harmonized_address_fields(): void
    {
        $response = $this->get(route('clients.create'));

        $response->assertOk();
        $response->assertSee('Editar la dirección completa y sus coordenadas desde un bloque compacto.');
        $response->assertSee('Intentar traer coordenadas');
        $response->assertSee('Número exterior');
        $response->assertSee('Interior');
        $response->assertSee('Código postal');
        $response->assertSee('Latitud');
        $response->assertSee('Longitud');
    }

    public function test_client_can_store_harmonized_address_fields(): void
    {
        $response = $this->post(route('clients.store'), [
            'first_name' => 'Lucia',
            'apellido_paterno' => 'Mena',
            'email' => 'lucia@example.com',
            'addresses' => [
                [
                    'type' => 'home',
                    'street' => 'Circuito Cumbres',
                    'exterior_number' => '24',
                    'interior_number' => 'II',
                    'colonia' => 'Cumbres Elite',
                    'city' => 'Monterrey',
                    'state' => 'Nuevo León',
                    'zip' => '64349',
                    'country' => 'México',
                    'lat' => '25.74123456',
                    'lng' => '-100.40123456',
                ],
            ],
            'phones' => [
                [
                    'type' => 'mobile',
                    'number' => '8112345678',
                ],
            ],
        ]);

        $response->assertRedirect(route('clients.index'));

        $client = Client::query()->where('email', 'lucia@example.com')->firstOrFail();
        $address = $client->addresses()->firstOrFail();

        $this->assertSame('24', $address->exterior_number);
        $this->assertSame('II', $address->interior_number);
        $this->assertSame('64349', $address->zip);
        $this->assertSame('Circuito Cumbres 24 Int II, Cumbres Elite, Monterrey, Nuevo León, 64349, México', $address->formatted_address);

        $showResponse = $this->get(route('clients.show', $client));
        $showResponse->assertOk();
        $showResponse->assertSee('Circuito Cumbres 24 Int II');
        $showResponse->assertSee('Maps');
    }

    public function test_client_edit_form_exposes_editable_address_cards_with_coordinate_tools(): void
    {
        $client = Client::create([
            'first_name' => 'Alicia',
            'apellido_paterno' => 'Prado',
            'email' => 'alicia@example.com',
        ]);

        $client->addresses()->create([
            'type' => 'home',
            'street' => 'Circuito Cumbres',
            'exterior_number' => '24',
            'interior_number' => 'II',
            'colonia' => 'Cumbres Elite',
            'city' => 'Monterrey',
            'state' => 'Nuevo León',
            'zip' => '64349',
            'country' => 'México',
        ]);

        $response = $this->get(route('clients.edit', $client));

        $response->assertOk();
        $response->assertSee('Editar la dirección completa y sus coordenadas desde un bloque compacto.');
        $response->assertSee('Intentar traer coordenadas');
        $response->assertSee('Mostrar en Google Maps');
        $response->assertSee('Importar punto exacto');
        $response->assertSee('Número exterior');
        $response->assertSee('Interior');
        $response->assertSee('address-editor-geocode-btn', false);
        $response->assertSee('address-editor-import-btn', false);
    }
}