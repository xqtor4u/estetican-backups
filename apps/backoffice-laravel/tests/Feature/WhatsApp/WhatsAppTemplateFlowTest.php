<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppTemplateFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-whatsapp-template-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    public function test_creating_a_template_via_json_returns_the_created_template_without_redirect(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.plantillas.store'), [
                'name' => 'Recordatorio rápido',
                'body' => 'Hola {cliente}, tu mascota {mascota} tiene una cita.',
                'context' => 'cita',
                'is_active' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonStructure(['template' => ['id', 'name']]);
        $response->assertJsonPath('template.name', 'Recordatorio rápido');

        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'Recordatorio rápido',
            'context' => 'cita',
        ]);
    }

    public function test_creating_a_template_via_normal_form_still_redirects(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('whatsapp.plantillas.store'), [
                'name' => 'Recordatorio de recurrencia',
                'body' => 'Hola {cliente}, {mascota} ya cumplió su ciclo de {servicio}.',
                'context' => 'recurrencia',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('whatsapp.plantillas.index'));

        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'Recordatorio de recurrencia',
            'context' => 'recurrencia',
        ]);
    }

    public function test_creating_a_template_via_json_with_invalid_data_returns_validation_errors(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('whatsapp.plantillas.store'), [
                'name' => '',
                'body' => '',
                'context' => 'cita',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'body']);
        $this->assertSame(0, WhatsAppTemplate::count());
    }
}
