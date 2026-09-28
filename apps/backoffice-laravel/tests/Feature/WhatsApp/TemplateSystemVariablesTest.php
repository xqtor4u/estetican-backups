<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Phone;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsApp\TemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * Variables del sistema en plantillas — {hora_local}/{sucursal}/{usuario} (quien envía y
 * cuándo) en todo contexto que envía una persona, y {precio_cita}/{precio_lista} solo en
 * contexto "cita", que es el único con una cita de por medio. "calendario" (sync automático)
 * no recibe ninguna.
 */
class TemplateSystemVariablesTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function senderWithBranch(): User
    {
        $branch = Branch::create(['code' => 'SUC-WA', 'name' => 'Sucursal Centro', 'is_active' => true]);

        return $this->createAdminUser(['first_name' => 'Lucía', 'branch_id' => $branch->id]);
    }

    private function clientWithPhone(): Client
    {
        $client = Client::create(['first_name' => 'Renata', 'apellido_paterno' => 'Vidal']);
        Phone::create(['client_id' => $client->id, 'type' => 'mobile', 'number' => '8110000001', 'sort_order' => 0]);

        return $client;
    }

    public function test_each_context_offers_only_the_variables_it_can_fill(): void
    {
        $cita = TemplateResolver::availableVariables('cita');
        $cliente = TemplateResolver::availableVariables('cliente');
        $calendario = TemplateResolver::availableVariables('calendario');

        foreach (['hora_local', 'sucursal', 'usuario', 'precio_cita', 'precio_lista'] as $key) {
            $this->assertArrayHasKey($key, $cita);
        }

        foreach (['hora_local', 'sucursal', 'usuario'] as $key) {
            $this->assertArrayHasKey($key, $cliente);
            $this->assertArrayHasKey($key, TemplateResolver::availableVariables('general'));
            $this->assertArrayHasKey($key, TemplateResolver::availableVariables('recurrencia'));
            $this->assertArrayNotHasKey($key, $calendario);
        }

        $this->assertArrayNotHasKey('precio_cita', $cliente);
        $this->assertArrayNotHasKey('precio_lista', TemplateResolver::availableVariables('general'));
    }

    public function test_cita_template_sent_from_a_booking_fills_system_and_price_variables(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-27 17:45:00'));
        $sender = $this->senderWithBranch();
        $client = $this->clientWithPhone();
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Habibi']);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => '2026-09-28 09:00:00',
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'total_estimated_price' => 230,
        ]);
        $service = Service::create(['code' => 'WA-VAR-1', 'name' => 'Baño perro chico', 'type' => 'spa', 'price' => 250, 'suggested_price' => 250, 'duration_minutes' => 60, 'is_active' => true]);
        SpaBookingService::create(['spa_booking_id' => $booking->id, 'service_id' => $service->id, 'current_price' => 230]);
        $template = WhatsAppTemplate::create([
            'name' => 'Confirmación',
            'body' => 'Son las {hora_local}. Soy {usuario} de {sucursal}: {mascota} cuesta {precio_cita} (lista {precio_lista}).',
            'context' => 'cita',
            'is_active' => true,
        ]);

        $message = $this->actingAs($sender)
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id.'&booking_id='.$booking->id)
            ->assertOk()
            ->json('message');

        $hora = Carbon::now()->format(config('backoffice.system.time_format') === '24h' ? 'H:i' : 'h:i A');
        $this->assertSame("Son las {$hora}. Soy Lucía de Sucursal Centro: Habibi cuesta \$230.00 (lista \$250.00).", $message);

        Carbon::setTestNow();
    }

    public function test_cliente_template_fills_system_variables_and_leaves_none_literal(): void
    {
        $sender = $this->senderWithBranch();
        $client = $this->clientWithPhone();
        $template = WhatsAppTemplate::create([
            'name' => 'Saludo',
            'body' => 'Hola {cliente}, te escribe {usuario} de {sucursal}.',
            'context' => 'cliente',
            'is_active' => true,
        ]);

        $message = $this->actingAs($sender)
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id)
            ->assertOk()
            ->json('message');

        $this->assertSame('Hola Renata Vidal, te escribe Lucía de Sucursal Centro.', $message);
    }

    public function test_sucursal_falls_back_to_the_only_active_branch_when_the_sender_has_none(): void
    {
        Branch::create(['code' => 'SUC-UNICA', 'name' => 'Matriz', 'is_active' => true]);
        $client = $this->clientWithPhone();
        $template = WhatsAppTemplate::create(['name' => 'S', 'body' => 'En {sucursal}', 'context' => 'cliente', 'is_active' => true]);

        $this->actingAs($this->createAdminUser())
            ->getJson(route('clients.whatsapp.link', $client).'?phone=8110000001&template_id='.$template->id)
            ->assertOk()
            ->assertJson(['message' => 'En Matriz']);
    }
}
