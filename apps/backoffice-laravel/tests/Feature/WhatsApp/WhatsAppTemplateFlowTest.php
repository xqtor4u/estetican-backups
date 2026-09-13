<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class WhatsAppTemplateFlowTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
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

    public function test_creating_a_template_via_normal_form_shows_the_success_message_only_once(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('whatsapp.plantillas.index'))
            ->post(route('whatsapp.plantillas.store'), [
                'name' => 'Recordatorio único',
                'body' => 'Hola {cliente}, {mascota} tiene una cita.',
                'context' => 'cita',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('whatsapp.plantillas.index'));

        $indexResponse = $this->get(route('whatsapp.plantillas.index'));
        $indexResponse->assertOk();

        // La vista tenía su propio banner "Plantilla creada correctamente" además del toast
        // global de layouts/app.blade.php — ambos leían session('success') y lo mostraban dos
        // veces. Solo debe quedar el toast global.
        $this->assertSame(
            1,
            substr_count($indexResponse->getContent(), 'Plantilla creada correctamente'),
        );
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

    /**
     * SYNC-096: el editor de plantillas (un solo _form.blade.php para WhatsApp y correo) ofrece
     * un selector de emoticones que reusa el mismo insert() de Alpine que los botones de
     * variables. Son emoji Unicode estándar, nunca stickers propios de una plataforma.
     */
    public function test_template_form_offers_a_standard_unicode_emoji_picker(): void
    {
        foreach ([route('whatsapp.plantillas.create'), route('whatsapp.plantillas.edit', WhatsAppTemplate::create([
            'name' => 'Base',
            'body' => 'Hola {cliente}',
            'context' => 'cita',
            'is_active' => true,
        ]))] as $url) {
            $response = $this->actingAs($this->admin())->get($url);

            $response->assertOk();
            $response->assertSee('Emoticones', false);
            $response->assertSee("insert('🐾')", false); // 🐾 vía @js()
        }
    }
}
