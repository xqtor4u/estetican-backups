<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetCatalogRootViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_pet_index_supports_blocks_and_table_views(): void
    {
        $client = Client::create([
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'email' => 'ana-pets@example.com',
        ]);

        Pet::create([
            'client_id' => $client->id,
            'name' => 'Nina',
            'species' => 'perro',
        ]);

        Pet::create([
            'client_id' => $client->id,
            'name' => 'Milo',
            'species' => 'gato',
            'death_date' => '2026-03-01',
        ]);

        $blocksResponse = $this->get(route('pets.index', ['view' => 'blocks']));

        $blocksResponse->assertOk();
        $blocksResponse->assertSee('Mascotas');
        $blocksResponse->assertSee('Nina');
        $blocksResponse->assertSee('Milo');
        $blocksResponse->assertSee('Bloques');

        $tableResponse = $this->get(route('pets.index', ['view' => 'table']));

        $tableResponse->assertOk();
        $tableResponse->assertSee('Tabla');
        $tableResponse->assertSee('Cliente');
        $tableResponse->assertSee('Fallecida');
        $tableResponse->assertSee('Aplicar');
    }

    public function test_root_pet_index_supports_filters_and_sorting(): void
    {
        $firstClient = Client::create([
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'email' => 'ana-filter@example.com',
        ]);

        $secondClient = Client::create([
            'first_name' => 'Beto',
            'last_name' => 'Mora',
            'email' => 'beto-filter@example.com',
        ]);

        Pet::create([
            'client_id' => $firstClient->id,
            'name' => 'Ares',
            'species' => 'gato',
            'death_date' => '2026-03-01',
        ]);

        Pet::create([
            'client_id' => $secondClient->id,
            'name' => 'Bruno',
            'species' => 'perro',
        ]);

        Pet::create([
            'client_id' => $firstClient->id,
            'name' => 'Zeus',
            'species' => 'perro',
        ]);

        $filteredResponse = $this->get(route('pets.index', [
            'view' => 'blocks',
            'search' => 'Bru',
            'species' => 'perro',
            'status' => 'active',
        ]));

        $filteredResponse->assertOk();
        $filteredResponse->assertSee('Bruno');
        $filteredResponse->assertDontSee('Ares');
        $filteredResponse->assertDontSee('Zeus');

        $sortedResponse = $this->get(route('pets.index', [
            'view' => 'table',
            'sort' => 'name',
            'direction' => 'desc',
        ]));

        $sortedResponse->assertOk();
        $sortedResponse->assertSeeInOrder(['Zeus', 'Bruno', 'Ares']);
        $sortedResponse->assertSee('Mascota', false);
        $sortedResponse->assertSee('↓', false);
    }

    public function test_root_pet_show_uses_root_breadcrumb_and_preserves_view_mode(): void
    {
        $client = Client::create([
            'first_name' => 'Laura',
            'last_name' => 'Mendez',
            'email' => 'laura-pets@example.com',
        ]);

        $selectedPet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Toby',
        ]);

        $siblingPet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Luna',
        ]);

        $response = $this->get(route('pets.show', ['pet' => $selectedPet, 'view' => 'table']));

        $response->assertOk();
        $response->assertSee('Detalle de mascota');
        $response->assertSee('Volver al listado');
        $response->assertSee('Cambiar entre mascotas del cliente');
        $response->assertSee('Luna');
        $response->assertSee(route('pets.show', ['pet' => $siblingPet, 'view' => 'table']), false);
    }

    public function test_root_pet_detail_allows_updating_pet_name_without_touching_client_edit(): void
    {
        $client = Client::create([
            'first_name' => 'Sofia',
            'last_name' => 'Ruiz',
            'email' => 'sofia-pets@example.com',
        ]);

        $pet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Bongo',
            'species' => 'perro',
        ]);

        $response = $this->put(route('pets.update', $pet), [
            'name' => 'Bongo Renombrado',
            'species' => 'perro',
            'breed' => 'Mestizo',
            'birth_date' => '',
            'death_date' => '',
            'microchip_code' => '',
            'tattoo_code' => '',
            'sex' => 'male',
            'coat_color' => 'Cafe',
            'size' => 'medium',
            'is_sterilized' => '1',
            'notes' => 'Actualizada desde ficha raiz',
            'return_view_mode' => 'table',
        ]);

        $response->assertRedirect(route('pets.show', ['pet' => $pet, 'view' => 'table']) . '#core-profile');

        $this->assertDatabaseHas('pets', [
            'id' => $pet->id,
            'name' => 'Bongo Renombrado',
            'breed' => 'Mestizo',
        ]);
    }
}