<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Pet;
use App\Models\PetPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientLivePetsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_table_sort_by_name_follows_displayed_first_name_order(): void
    {
        Client::create([
            'first_name' => 'Zoe',
            'last_name' => 'Alvarez',
            'email' => 'zoe@example.com',
        ]);

        Client::create([
            'first_name' => 'Ana',
            'last_name' => 'Zuluaga',
            'email' => 'ana@example.com',
        ]);

        Client::create([
            'first_name' => 'Beto',
            'last_name' => 'Mora',
            'email' => 'beto@example.com',
        ]);

        $response = $this->get(route('clients.index', [
            'view' => 'table',
            'sort' => 'first_name',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        // Verifica que los nombres estén en orden en la tabla
        $response->assertSeeTextInOrder(['Ana', 'Beto', 'Zoe']);
        $response->assertSeeTextInOrder(['Zuluaga', 'Mora', 'Alvarez']);
    }

    public function test_clients_table_exposes_separate_name_and_last_name_sort_headers(): void
    {
        $client = Client::create([
            'first_name' => 'Rosa',
            'last_name' => 'Diaz',
            'email' => 'rosa-split@example.com',
        ]);

        $response = $this->get(route('clients.index', [
            'view' => 'table',
            'sort' => 'last_name',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $response->assertSee('Nombre');
        $response->assertSee('Apellido');
        $response->assertSee('Rosa');
        $response->assertSee('Diaz');
        $response->assertSee('sort=first_name', false);
        $response->assertSee('direction=asc', false);
        $response->assertSee('sort=last_name', false);
        $response->assertSee('direction=desc', false);
    }

    public function test_client_pages_show_only_live_pets_with_thumbnail_blocks(): void
    {
        $client = Client::create([
            'first_name' => 'Rosa',
            'last_name' => 'Diaz',
            'email' => 'rosa@example.com',
        ]);

        $livePet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Nina',
            'species' => 'Perro',
        ]);

        $deceasedPet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Luna',
            'species' => 'Perro',
            'death_date' => '2026-03-01',
        ]);

        PetPhoto::create([
            'pet_id' => $livePet->id,
            'photo_url' => 'pet-photos/2026/03/original/nina.jpg',
            'photo_type' => 'perfil',
            'is_primary' => true,
        ]);

        PetPhoto::create([
            'pet_id' => $deceasedPet->id,
            'photo_url' => 'pet-photos/2026/03/original/luna.jpg',
            'photo_type' => 'perfil',
            'is_primary' => true,
        ]);

        $indexResponse = $this->get(route('clients.index'));
        $showResponse = $this->get(route('clients.show', $client));
        $editResponse = $this->get(route('clients.edit', $client));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Mascotas vivas');
        $indexResponse->assertSee('Nina');
        $indexResponse->assertDontSee('Luna');
        $indexResponse->assertSee('/storage/pet-photos/2026/03/thumbs/nina.jpg');

        $showResponse->assertOk();
        $showResponse->assertSee('Mascotas vivas');
        $showResponse->assertSee('Nina');
        $showResponse->assertDontSee('Luna');
        $showResponse->assertSee('/storage/pet-photos/2026/03/thumbs/nina.jpg');

        $editResponse->assertOk();
        $editResponse->assertSee('Seleccionar mascota para gestionar tablas dependientes');
        $editResponse->assertSee('Nina');
        $editResponse->assertSee('Luna');
        $editResponse->assertSee('/storage/pet-photos/2026/03/thumbs/nina.jpg');
        $editResponse->assertSee('/storage/pet-photos/2026/03/thumbs/luna.jpg');
    }
}