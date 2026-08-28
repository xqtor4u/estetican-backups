<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * `GET /api/clients` y `GET /api/pets` ganaron paginación **opcional** vía `per_page`
 * sin romper a los clientes existentes (la app móvil): sin el parámetro devuelven el
 * arreglo completo como antes; con él devuelven el paginador de Laravel.
 */
class ListingPaginationTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function seedClients(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            $client = Client::create(['first_name' => 'Cli'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'apellido_paterno' => 'Test']);
            Pet::create(['client_id' => $client->id, 'name' => 'Mascota'.$i]);
        }
    }

    public function test_clients_without_per_page_returns_a_bare_array(): void
    {
        $this->seedClients(3);

        $response = $this->withHeaders($this->createAdminAuthHeader())->getJson('/api/clients');

        $response->assertOk();
        $response->assertJsonCount(3); // arreglo plano de 3, sin envoltura de paginador
        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_clients_with_per_page_returns_a_laravel_paginator(): void
    {
        $this->seedClients(5);

        $response = $this->withHeaders($this->createAdminAuthHeader())->getJson('/api/clients?per_page=2');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'current_page', 'per_page', 'total', 'last_page']);
        $response->assertJsonPath('per_page', 2);
        $response->assertJsonPath('total', 5);
        $response->assertJsonPath('last_page', 3);
        $response->assertJsonCount(2, 'data');
    }

    public function test_pets_with_per_page_returns_a_laravel_paginator(): void
    {
        $this->seedClients(4);

        $response = $this->withHeaders($this->createAdminAuthHeader())->getJson('/api/pets?per_page=3');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'current_page', 'per_page', 'total', 'last_page']);
        $response->assertJsonPath('per_page', 3);
        $response->assertJsonPath('total', 4);
        $response->assertJsonCount(3, 'data');
    }

    public function test_per_page_is_capped_at_100_and_floored_at_1(): void
    {
        $this->seedClients(2);
        $headers = $this->createAdminAuthHeader();

        $this->withHeaders($headers)->getJson('/api/clients?per_page=9999')
            ->assertOk()->assertJsonPath('per_page', 100);

        $this->withHeaders($headers)->getJson('/api/clients?per_page=0')
            ->assertOk()->assertJsonPath('per_page', 1);
    }
}
