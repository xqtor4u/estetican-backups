<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRestrictedOperatorUser;
use Tests\TestCase;

/**
 * Mismo scope que `Api\AgendaOperatorScopingTest`, del lado del backoffice web —
 * `SpaBookingController` reusa `SpaBooking::visibleTo()` vía `applyBookingFilters()`/
 * `ensureVisible()`. Hoy el perfil restringido es solo móvil, pero la ruta web ya está
 * gateada por `permission:ver agenda` igual que la API — el scope debe aplicar igual si
 * algún día entra al backoffice por URL directa.
 */
class SpaBookingControllerWebScopingTest extends TestCase
{
    use CreatesRestrictedOperatorUser;
    use RefreshDatabase;

    private function operator(string $name = 'Op'): Operator
    {
        return Operator::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    private function booking(Operator $operator): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => '2026-09-01 10:00:00',
            'status' => 'scheduled',
            'duration_minutes' => 60,
            'total_estimated_price' => 100,
        ]);
    }

    public function test_restricted_operator_index_table_only_lists_own_bookings(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $myBooking = $this->booking($mine);
        $otherBooking = $this->booking($theirs);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->actingAs($user)->get(route('agenda.index', ['date_scope' => 'custom', 'date' => '2026-09-01']));

        $response->assertOk();
        $response->assertViewHas('bookings', function ($bookings) use ($myBooking, $otherBooking) {
            $ids = collect($bookings->items())->pluck('id')->all();

            return in_array($myBooking->id, $ids, true) && ! in_array($otherBooking->id, $ids, true);
        });
    }

    public function test_restricted_operator_only_sees_own_blocked_unavailability_windows(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $this->booking($mine);
        $mine->unavailabilities()->create(['starts_at' => '2026-09-01 09:00:00', 'ends_at' => '2026-09-01 13:00:00']);
        $theirs->unavailabilities()->create(['starts_at' => '2026-09-01 09:00:00', 'ends_at' => '2026-09-01 13:00:00']);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->actingAs($user)->get(route('agenda.index', ['date_scope' => 'custom', 'date' => '2026-09-01']));

        $response->assertOk();
        $response->assertViewHas('blockedToday', function ($windows) use ($mine) {
            return $windows->count() === 1 && $windows->first()->operator_id === $mine->id;
        });
    }

    public function test_restricted_operator_gets_404_on_show_of_someone_elses_booking(): void
    {
        $mine = $this->operator('Mine');
        $theirs = $this->operator('Theirs');
        $otherBooking = $this->booking($theirs);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->actingAs($user)->get(route('agenda.show', $otherBooking));

        $response->assertNotFound();
    }

    public function test_restricted_operator_can_view_their_own_booking(): void
    {
        $mine = $this->operator('Mine');
        $myBooking = $this->booking($mine);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->actingAs($user)->get(route('agenda.show', $myBooking));

        $response->assertOk();
    }

    public function test_restricted_operator_without_client_pet_permissions_does_not_see_dead_end_buttons_in_index(): void
    {
        $mine = $this->operator('Mine');
        $this->booking($mine);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->actingAs($user)->get(route('agenda.index', ['date_scope' => 'custom', 'date' => '2026-09-01']));

        $response->assertOk();
        $response->assertDontSee('btn-outline-primary">Mascota</a>', false);
        $response->assertDontSee('btn-outline-secondary">Cliente</a>', false);
    }

    public function test_restricted_operator_without_pet_permission_does_not_see_dead_end_button_in_show(): void
    {
        $mine = $this->operator('Mine');
        $myBooking = $this->booking($mine);

        $user = $this->createOperatorUser(['ver agenda'], $mine);

        $response = $this->actingAs($user)->get(route('agenda.show', $myBooking));

        $response->assertOk();
        $response->assertDontSee('Perfil de mascota');
    }
}
