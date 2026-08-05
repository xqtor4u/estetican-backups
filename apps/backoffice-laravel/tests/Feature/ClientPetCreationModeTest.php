<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class ClientPetCreationModeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    public function test_index_in_pet_creation_mode_shows_add_pet_action_instead_of_ver_editar(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        $response = $this->actingAs($this->admin())->get(route('clients.index', ['mode' => 'pet_creation']));

        $response->assertOk();
        $response->assertSee('Agregar mascota aquí');
        $response->assertSee(route('clients.edit', ['client' => $client, 'open_pet_modal' => 1]), false);
        $response->assertDontSee('Eliminar');
    }

    public function test_index_without_pet_creation_mode_shows_normal_actions(): void
    {
        Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('clients.index'));

        $response->assertOk();
        $response->assertDontSee('Agregar mascota aquí');
        $response->assertSee('Editar');
    }

    public function test_pet_creation_mode_survives_a_search_submit(): void
    {
        Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('clients.index', ['mode' => 'pet_creation']));

        $response->assertOk();
        $response->assertSee('name="mode" value="pet_creation"', false);
    }

    public function test_index_table_view_in_pet_creation_mode_shows_add_pet_action(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('clients.index', ['mode' => 'pet_creation', 'view' => 'table']));

        $response->assertOk();
        $response->assertSee('Agregar mascota aquí');
        $response->assertSee(route('clients.edit', ['client' => $client, 'open_pet_modal' => 1]), false);
    }

    public function test_edit_page_loads_fine_with_the_open_pet_modal_flag(): void
    {
        // La auto-apertura del modal vive en client-form.js (lee `open_pet_modal` de
        // window.location.search en tiempo de ejecución, dentro de initClientEditForm()
        // para evitar una condición de carrera con el registro de listeners — ver NT-043
        // en NOTAS_TECNICAS.md). No hay diferencia en el HTML servido entre tenerlo o no,
        // así que aquí solo se confirma que la ruta no truena con el parámetro presente.
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('clients.edit', ['client' => $client, 'open_pet_modal' => 1]));

        $response->assertOk();
        $response->assertSee('data-client-edit-action="show-pet-modal"', false);
    }
}
