<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRestrictedOperatorUser;
use Tests\TestCase;

/**
 * Un operador restringido (solo `ver agenda`, sin `ver clientes`/`ver mascotas`/
 * `ver operadores`) no debe poder listar/consultar el directorio completo de clientes,
 * mascotas u operadores — decisión deliberada del diseño (ver plan "operador restringido"):
 * solo ve esos datos embebidos en sus propias citas, nunca vía estos endpoints.
 */
class RestrictedOperatorClientPetAccessTest extends TestCase
{
    use CreatesRestrictedOperatorUser;
    use RefreshDatabase;

    public function test_restricted_operator_cannot_list_clients(): void
    {
        $user = $this->createOperatorUser(['ver agenda']);

        $response = $this->withHeaders($this->operatorAuthHeader($user))->getJson('/api/clients');

        $response->assertForbidden();
    }

    public function test_restricted_operator_cannot_list_pets(): void
    {
        $user = $this->createOperatorUser(['ver agenda']);

        $response = $this->withHeaders($this->operatorAuthHeader($user))->getJson('/api/pets');

        $response->assertForbidden();
    }

    public function test_restricted_operator_cannot_list_operators(): void
    {
        $user = $this->createOperatorUser(['ver agenda']);

        $response = $this->withHeaders($this->operatorAuthHeader($user))->getJson('/api/operators');

        $response->assertForbidden();
    }

    public function test_a_user_explicitly_granted_ver_clientes_can_list_clients(): void
    {
        $user = $this->createOperatorUser(['ver agenda', 'ver clientes']);

        $response = $this->withHeaders($this->operatorAuthHeader($user))->getJson('/api/clients');

        $response->assertOk();
    }
}
