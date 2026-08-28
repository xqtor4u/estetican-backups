<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * "Iniciar cita" en el web (`POST agenda/{booking}/iniciar`): promueve una cita
 * Programada directo a Orden de Trabajo sin pasar por un presupuesto, arrancando las
 * líneas de servicio pendientes (paridad con la app móvil).
 */
class SpaBookingStartTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function bookingWithLines(string $status = 'scheduled', int $lines = 2): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0),
            'status' => $status,
            'total_estimated_price' => 0,
        ]);

        for ($i = 0; $i < $lines; $i++) {
            $service = Service::create(['code' => 'S'.uniqid(), 'name' => 'Servicio '.$i, 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30]);
            $booking->services()->create(['service_id' => $service->id, 'current_price' => 100]);
        }

        return $booking;
    }

    public function test_start_promotes_a_scheduled_booking_to_work_order_and_starts_every_line(): void
    {
        $booking = $this->bookingWithLines();

        $response = $this->actingAs($this->createAdminUser())
            ->post(route('agenda.start', $booking));

        $response->assertRedirect(route('agenda.show', $booking));
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('work_order', $booking->status);
        $this->assertTrue($booking->services->every(fn ($line) => $line->started_at !== null));
    }

    public function test_start_with_service_line_ids_only_starts_the_chosen_lines(): void
    {
        $booking = $this->bookingWithLines('scheduled', lines: 3);
        $lines = $booking->services()->orderBy('id')->get();
        $chosen = $lines->first();

        $response = $this->actingAs($this->createAdminUser())
            ->post(route('agenda.start', $booking), ['service_line_ids' => [$chosen->id]]);

        $response->assertRedirect(route('agenda.show', $booking));
        $booking->refresh();
        $this->assertSame('work_order', $booking->status); // la cita igual pasa a orden de trabajo
        $this->assertNotNull($chosen->fresh()->started_at);
        $this->assertNull($lines->get(1)->fresh()->started_at); // las no elegidas quedan pendientes
        $this->assertNull($lines->get(2)->fresh()->started_at);
    }

    public function test_start_with_an_empty_service_line_ids_selection_is_rejected(): void
    {
        $booking = $this->bookingWithLines('scheduled', lines: 2);

        $this->actingAs($this->createAdminUser())
            ->post(route('agenda.start', $booking), ['service_line_ids' => ['999999']])
            ->assertRedirect(route('agenda.show', $booking))
            ->assertSessionHas('error');

        $this->assertSame('scheduled', $booking->fresh()->status);
    }

    public function test_start_is_rejected_when_the_booking_is_not_scheduled(): void
    {
        $booking = $this->bookingWithLines('work_order');

        $this->actingAs($this->createAdminUser())
            ->post(route('agenda.start', $booking))
            ->assertRedirect(route('agenda.show', $booking))
            ->assertSessionHas('error');

        $this->assertSame('work_order', $booking->refresh()->status);
    }

    public function test_start_is_rejected_when_the_booking_has_no_services(): void
    {
        $booking = $this->bookingWithLines('scheduled', lines: 0);

        $this->actingAs($this->createAdminUser())
            ->post(route('agenda.start', $booking))
            ->assertRedirect(route('agenda.show', $booking))
            ->assertSessionHas('error');

        $this->assertSame('scheduled', $booking->refresh()->status);
    }

    public function test_start_requires_the_editar_agenda_permission(): void
    {
        $booking = $this->bookingWithLines();
        $user = $this->createAdminUser();
        $user->syncRoles([]);
        $user->syncPermissions([]);

        $this->actingAs($user)
            ->post(route('agenda.start', $booking))
            ->assertForbidden();

        $this->assertSame('scheduled', $booking->refresh()->status);
    }
}
