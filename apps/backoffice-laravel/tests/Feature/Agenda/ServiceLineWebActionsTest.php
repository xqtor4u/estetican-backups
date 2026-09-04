<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * `PATCH agenda/{booking}/services/{line}` — acciones por línea de servicio desde el web
 * (pop-up de la agenda), misma lógica que el móvil vía `ServiceLineActionService`.
 */
class ServiceLineWebActionsTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function bookingWithLine(string $status = 'scheduled'): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'S'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 200, 'duration_minutes' => 30, 'is_active' => true]);
        $booking = SpaBooking::create([
            'pet_id' => $pet->id, 'scheduled_at' => now()->addHours(2),
            'status' => $status, 'total_estimated_price' => 200, 'duration_minutes' => 30,
        ]);
        $line = $booking->services()->create(['service_id' => $service->id, 'current_price' => 200]);

        return [$booking, $line];
    }

    public function test_mark_started_starts_the_line_and_promotes_the_booking(): void
    {
        [$booking, $line] = $this->bookingWithLine('scheduled');

        $this->actingAs($this->createAdminUser())
            ->patch(route('agenda.services.update', [$booking, $line]), ['mark_started' => '1'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($line->fresh()->started_at);
        $this->assertSame('work_order', $booking->fresh()->status);
    }

    public function test_reassign_operator_on_a_line(): void
    {
        [$booking, $line] = $this->bookingWithLine('work_order');
        $op = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Nueva', 'first_name' => 'Nueva', 'is_active' => true]);

        $this->actingAs($this->createAdminUser())
            ->patch(route('agenda.services.update', [$booking, $line]), ['operator_id' => $op->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame($op->id, $line->fresh()->operator_id);
    }

    public function test_mark_not_performed_excludes_the_line_from_the_total(): void
    {
        [$booking, $line] = $this->bookingWithLine('work_order');
        $other = Service::create(['code' => 'S'.uniqid(), 'name' => 'Corte', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 20, 'is_active' => true]);
        $booking->services()->create(['service_id' => $other->id, 'current_price' => 100]);
        $booking->update(['total_estimated_price' => 300]);

        $this->actingAs($this->createAdminUser())
            ->patch(route('agenda.services.update', [$booking, $line]), ['mark_not_performed' => '1'])
            ->assertRedirect();

        $this->assertNotNull($line->fresh()->not_performed_at);
        $this->assertSame('100.00', (string) $booking->fresh()->total_estimated_price);
    }

    public function test_cannot_complete_a_line_that_never_started(): void
    {
        [$booking, $line] = $this->bookingWithLine('work_order');

        $this->actingAs($this->createAdminUser())
            ->patch(route('agenda.services.update', [$booking, $line]), ['mark_completed' => '1'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertNull($line->fresh()->completed_at);
    }

    public function test_rejects_a_line_from_another_booking(): void
    {
        [$booking] = $this->bookingWithLine();
        [, $foreignLine] = $this->bookingWithLine();

        $this->actingAs($this->createAdminUser())
            ->patch(route('agenda.services.update', [$booking, $foreignLine]), ['mark_started' => '1'])
            ->assertNotFound();
    }

    public function test_requires_editar_agenda_permission(): void
    {
        [$booking, $line] = $this->bookingWithLine();
        $user = $this->createAdminUser();
        $user->syncRoles([]);
        $user->syncPermissions([]);

        $this->actingAs($user)
            ->patch(route('agenda.services.update', [$booking, $line]), ['mark_started' => '1'])
            ->assertForbidden();
    }

    public function test_rejects_when_the_booking_is_already_closed(): void
    {
        [$booking, $line] = $this->bookingWithLine('completed');

        $this->actingAs($this->createAdminUser())
            ->patch(route('agenda.services.update', [$booking, $line]), ['mark_started' => '1'])
            ->assertRedirect()->assertSessionHas('error');
    }

    /**
     * SYNC-082: el pop-up de la agenda pide JSON (fetch) para no cerrarse — la respuesta
     * trae el estado fresco de TODAS las líneas para re-pintar el panel en su lugar.
     */
    public function test_json_request_returns_fresh_line_states_without_a_redirect(): void
    {
        [$booking, $line] = $this->bookingWithLine('work_order');
        $other = Service::create(['code' => 'S'.uniqid(), 'name' => 'Corte', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 20, 'is_active' => true]);
        $line2 = $booking->services()->create(['service_id' => $other->id, 'current_price' => 100]);

        $res = $this->actingAs($this->createAdminUser())
            ->patchJson(route('agenda.services.update', [$booking, $line]), ['mark_started' => '1']);

        $res->assertOk()->assertJson(['ok' => true]);
        $res->assertJsonCount(2, 'lines');
        $this->assertSame('in_progress', collect($res->json('lines'))->firstWhere('id', $line->id)['state']);
        $this->assertSame('pending', collect($res->json('lines'))->firstWhere('id', $line2->id)['state']);
    }

    public function test_json_request_returns_422_with_a_message_on_error(): void
    {
        [$booking, $line] = $this->bookingWithLine('completed');

        $this->actingAs($this->createAdminUser())
            ->patchJson(route('agenda.services.update', [$booking, $line]), ['mark_started' => '1'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }
}
