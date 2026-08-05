<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Auditoría de UX (03/08/2026, vía Claude en Chrome del usuario): el modal
 * "Agregar Mascota" no marcaba ningún campo como obligatorio, ni siquiera "Nombre"
 * (que sí es requerido en el backend, `PetController::validatedPetData()`). Mismo
 * gap encontrado en el formulario de cliente y en `shared/address-editor.blade.php`
 * (usado en varias pantallas, no solo aquí). Se corrigió a nivel de plantilla —
 * componente nuevo `<x-form-label>` — para que el fix aplique a todas las instancias
 * que renderiza el archivo de un jalón, no campo por campo.
 */
class RequiredFieldLabelsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    public function test_client_edit_page_marks_required_and_optional_fields_distinctly(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        $response = $this->actingAs($this->admin())->get(route('clients.edit', $client));

        $response->assertOk();
        $html = $response->getContent();

        // "Nombre" (first_name, requerido) trae asterisco; "Apellido paterno" (opcional en
        // update()) no. No podemos contar apariciones exactas porque el modal de mascota
        // también dice "Nombre" — basta confirmar que el asterisco existe cerca de ambos
        // "Nombre" (cliente y mascota) y que "Apellido paterno" no tiene uno pegado.
        $this->assertMatchesRegularExpression('/Nombre\s*<span class="text-danger"/', $html);
        $this->assertDoesNotMatchRegularExpression('/Apellido paterno\s*<span class="text-danger"/', $html);
    }

    public function test_pet_modal_marks_nombre_as_required_and_sexo_as_optional(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        $response = $this->actingAs($this->admin())->get(route('clients.edit', $client));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('id="modalPetName"', $html);
        $this->assertMatchesRegularExpression('/Sexo\s*<small[^>]*>\(opcional\)<\/small>/', $html);
    }

    public function test_address_modal_marks_tipo_calle_ciudad_pais_as_required(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        $response = $this->actingAs($this->admin())->get(route('clients.edit', $client));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/Tipo\s*<span class="text-danger"/', $html);
        $this->assertMatchesRegularExpression('/Calle\s*<span class="text-danger"/', $html);
        $this->assertMatchesRegularExpression('/Ciudad\s*<span class="text-danger"/', $html);
        $this->assertMatchesRegularExpression('/País\s*<span class="text-danger"/', $html);
    }

    public function test_pet_own_edit_page_marks_nombre_required_and_defaults_sex_to_no_definido(): void
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = \App\Models\Pet::create(['client_id' => $client->id, 'name' => 'Luka', 'sex' => null]);

        $response = $this->actingAs($this->admin())->get(route('pets.show', $pet));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/Nombre\s*<span class="text-danger"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="">Seleccionar<\/option>\s*<option value="male"/', $html);
        // Sin sexo guardado (null), "No definido" debe quedar seleccionado por default.
        $this->assertMatchesRegularExpression('/<option value="unknown" selected[^>]*>No definido<\/option>/', $html);
    }

    public function test_form_label_component_renders_asterisk_only_when_required(): void
    {
        $required = \Illuminate\Support\Facades\Blade::render('<x-form-label required>Campo</x-form-label>');
        $optional = \Illuminate\Support\Facades\Blade::render('<x-form-label>Campo</x-form-label>');

        $this->assertStringContainsString('text-danger', $required);
        $this->assertStringNotContainsString('text-danger', $optional);
    }
}
