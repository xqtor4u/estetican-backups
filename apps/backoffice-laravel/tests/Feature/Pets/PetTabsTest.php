<?php

namespace Tests\Feature\Pets;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * La ficha de mascota pasó de una sola página larga a pestañas (Resumen/Agenda/Servicios/
 * Historial/Cobros + Veterinaria como link aparte) — a pedido del usuario (16/08/2026), mismo
 * principio de organización ya aplicado con los reportes de Caja. Servicios/Historial/Cobros son
 * pestañas nuevas con datos reales (no "próximamente"): reusan `SpaBooking::totalPaid()`/
 * `unpaidBalance()` (única fuente de verdad ya usada por Agenda) en vez de recalcular montos.
 */
class PetTabsTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function petWithCompletedBooking(float $price = 400): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka'.uniqid()]);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño y corte', 'type' => 'grooming', 'price' => $price, 'duration_minutes' => 60, 'is_active' => true]);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Operador Test'.uniqid(), 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->subDays(3),
            'status' => 'completed',
            'total_estimated_price' => $price,
        ]);

        SpaBookingService::create([
            'spa_booking_id' => $booking->id,
            'service_id' => $service->id,
            'operator_id' => $operator->id,
            'current_price' => $price,
        ]);

        Payment::create([
            'client_id' => $client->id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => $price - 50,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
            'category' => 'liquidacion',
        ]);

        return $pet;
    }

    public function test_pet_show_renders_with_default_resumen_tab_active(): void
    {
        $pet = $this->petWithCompletedBooking();

        $response = $this->actingAs($this->admin())->get(route('pets.show', $pet));

        $response->assertOk();
        $response->assertSee('id="pet-tab-resumen"', false);
        $response->assertSee('show active', false);
    }

    public function test_servicios_tab_shows_the_real_service_operator_and_price(): void
    {
        $pet = $this->petWithCompletedBooking(400);

        $response = $this->actingAs($this->admin())->get(route('pets.show', ['pet' => $pet, 'tab' => 'servicios']));

        $response->assertOk();
        $response->assertSee('Baño y corte');
        $response->assertSee('Operador Test', false);
        $response->assertSee('400.00');
    }

    public function test_historial_tab_shows_the_past_booking_with_its_status(): void
    {
        $pet = $this->petWithCompletedBooking(400);

        $response = $this->actingAs($this->admin())->get(route('pets.show', ['pet' => $pet, 'tab' => 'historial']));

        $response->assertOk();
        $response->assertSee('Completed');
        $response->assertSee('400.00');
    }

    public function test_cobros_tab_shows_real_totalpaid_and_unpaidbalance(): void
    {
        $pet = $this->petWithCompletedBooking(400);

        $response = $this->actingAs($this->admin())->get(route('pets.show', ['pet' => $pet, 'tab' => 'cobros']));

        $response->assertOk();
        // $400 estimado, $350 cobrado (400 - 50), $50 pendiente — misma cuenta que
        // SpaBooking::totalPaid()/unpaidBalance() ya usan en Agenda.
        $response->assertSee('350.00');
        $response->assertSee('50.00');
    }

    public function test_active_tab_query_param_marks_the_right_pane_as_shown(): void
    {
        $pet = $this->petWithCompletedBooking();

        $response = $this->actingAs($this->admin())->get(route('pets.show', ['pet' => $pet, 'tab' => 'cobros']));

        $response->assertOk();
        $response->assertSee('id="pet-tab-cobros"', false);
    }

    /**
     * Veterinaria es un módulo separado (`clinical.*`), nunca una pestaña dentro de la ficha
     * compartida de mascota — a propósito, para no acoplar visualmente los dos flujos (hallazgo
     * real 16/08/2026: una primera versión sí mezclaba una pestaña "Veterinaria" aquí, revertido
     * de inmediato a pedido del usuario). El único puente sigue siendo el botón "Ver expediente
     * clínico" que ya existía antes de esta sesión.
     */
    public function test_pets_show_never_renders_a_veterinaria_tab(): void
    {
        $pet = $this->petWithCompletedBooking();

        $response = $this->actingAs($this->admin())->get(route('pets.show', $pet));

        $response->assertOk();
        $response->assertDontSee('id="pet-tab-veterinaria"', false);
    }
}
