<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\CreatesRestrictedOperatorUser;
use Tests\TestCase;

/**
 * Un operador restringido (`ver agenda` sin `agenda.ver_todas`, no super-admin) debe ver
 * solo sus propias citas — nunca las de otro operador — en los endpoints de agenda, y
 * recibir 404 (no 403, no confirmar existencia) si intenta acceder por ID a una cita ajena.
 */
class AgendaOperatorScopingTest extends TestCase
{
    use CreatesAdminUser;
    use CreatesRestrictedOperatorUser;
    use RefreshDatabase;

    private function operator(string $name = 'Op'): Operator
    {
        return Operator::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    private function booking(Operator $operator, string $scheduledAt = '2026-09-01 10:00:00', string $status = 'scheduled'): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt,
            'status' => $status,
            'duration_minutes' => 60,
            'total_estimated_price' => 100,
        ]);
    }

    public function test_restricted_operator_only_sees_own_bookings_in_index(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $myBooking = $this->booking($mine);
        $this->booking($theirs);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson('/api/agenda?date=2026-09-01');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertSame([$myBooking->id], $ids);
    }

    public function test_restricted_operator_sees_own_booking_via_quote_item_operator_not_just_column(): void
    {
        // La cita está asignada por columna a "theirs", pero el presupuesto aceptado tiene un
        // ítem con "mine" como operador — mismo criterio que AgendaController::index() usa
        // para armar la lista de operadores por cita (unión operator_id + items del quote
        // aceptado). Sin cubrir este camino, un operador con un servicio asignado vía
        // presupuesto (no la columna directa) nunca vería esa cita.
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $booking = $this->booking($theirs);
        $service = Service::create(['code' => 'SVC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30, 'is_active' => true]);
        $quote = Quote::create(['spa_booking_id' => $booking->id, 'status' => 'accepted', 'total_amount' => 100]);
        $quote->items()->create(['service_id' => $service->id, 'quantity' => 1, 'price_override' => 100, 'operator_id' => $mine->id]);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson('/api/agenda?date=2026-09-01');

        $response->assertOk();
        $this->assertSame([$booking->id], collect($response->json())->pluck('id')->all());
    }

    public function test_restricted_operator_cannot_widen_scope_by_sending_operator_id(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $this->booking($mine);
        $otherBooking = $this->booking($theirs);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson("/api/agenda?date=2026-09-01&operator_id={$theirs->id}");

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertNotContains($otherBooking->id, $ids);
    }

    public function test_operator_without_linked_operator_id_sees_nothing_instead_of_erroring(): void
    {
        $theirs = $this->operator('Theirs');
        $this->booking($theirs);

        $user = $this->createOperatorUser(['ver agenda'], null);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson('/api/agenda?date=2026-09-01');

        $response->assertOk();
        $this->assertSame([], $response->json());
    }

    public function test_user_with_ver_todas_sees_every_operators_bookings(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $this->booking($mine);
        $this->booking($theirs);

        $user = $this->createOperatorUser(['ver agenda', 'agenda.ver_todas'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson('/api/agenda?date=2026-09-01');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_admin_sees_every_operators_bookings(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $this->booking($mine);
        $this->booking($theirs);

        $response = $this->withHeaders($this->createAdminAuthHeader())
            ->getJson('/api/agenda?date=2026-09-01');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_restricted_operator_gets_404_on_show_of_someone_elses_booking(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $otherBooking = $this->booking($theirs);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson("/api/bookings/{$otherBooking->id}");

        $response->assertNotFound();
    }

    public function test_restricted_operator_can_show_their_own_booking(): void
    {
        $mine = $this->operator('Mine');
        $myBooking = $this->booking($mine);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson("/api/bookings/{$myBooking->id}");

        $response->assertOk();
    }

    public function test_restricted_operator_gets_404_updating_someone_elses_booking(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $otherBooking = $this->booking($theirs);

        // 'editar agenda' de más a propósito: sin él, la ruta ya rechaza con 403 antes de
        // llegar al controller — este test quiere ejercitar puntualmente el scope de
        // ensureVisible(), no el gate de permiso de la ruta (ya cubierto en otro lado).
        $user = $this->createOperatorUser(['ver agenda', 'editar agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->patchJson("/api/bookings/{$otherBooking->id}", ['notes' => 'intento ajeno']);

        $response->assertNotFound();
    }

    public function test_restricted_operator_gets_404_on_payments_of_someone_elses_booking(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $otherBooking = $this->booking($theirs);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson("/api/bookings/{$otherBooking->id}/payments");

        $response->assertNotFound();
    }

    public function test_restricted_operator_gets_404_on_process_notes_of_someone_elses_booking(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $otherBooking = $this->booking($theirs);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson("/api/bookings/{$otherBooking->id}/process-notes");

        $response->assertNotFound();
    }

    public function test_restricted_operator_only_sees_own_vencidas(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        // "vencida": scheduled del pasado, nunca iniciada
        $myBooking = $this->booking($mine, '2020-01-01 09:00:00');
        $this->booking($theirs, '2020-01-01 09:00:00');

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $this->assertSame([$myBooking->id], collect($response->json())->pluck('id')->all());
    }

    public function test_restricted_operator_only_sees_own_unavailabilities(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $mine->unavailabilities()->create(['starts_at' => '2026-09-01 09:00:00', 'ends_at' => '2026-09-01 13:00:00']);
        $theirs->unavailabilities()->create(['starts_at' => '2026-09-01 09:00:00', 'ends_at' => '2026-09-01 13:00:00']);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->withHeaders($this->operatorAuthHeader($user))
            ->getJson('/api/agenda/unavailabilities?date=2026-09-01');

        $response->assertOk();
        $windows = $response->json();
        $this->assertCount(1, $windows);
        $this->assertSame($mine->id, $windows[0]['operator_id']);
    }
}
