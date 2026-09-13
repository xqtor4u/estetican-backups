<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * SYNC-085 (portado desde Zeus) — la agenda debe seguir siempre el ajuste
 * "Formato de hora" del sistema, en vez de mezclar 12h/24h según la pantalla.
 */
class AgendaTimeFormatSyncTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    public function test_scheduled_at_field_no_longer_forces_24h_in_create(): void
    {
        $response = $this->actingAs($this->admin())->get(route('pets.bookings.create', $this->pet()));

        $response->assertOk();
        $response->assertDontSee('data-force-24h', false);
    }

    public function test_scheduled_at_field_no_longer_forces_24h_in_edit(): void
    {
        $pet = $this->pet();
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay()->setTime(11, 0),
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $response = $this->actingAs($this->admin())->get(route('agenda.edit', $booking));

        $response->assertOk();
        $response->assertDontSee('data-force-24h', false);
    }

    public function test_operating_hours_hint_follows_system_time_format_in_12h(): void
    {
        app(SystemSettings::class)->saveFields('system', ['system_time_format' => '12h']);

        $response = $this->actingAs($this->admin())->get(route('pets.bookings.create', $this->pet()));

        $response->assertOk();
        $response->assertSee('Horario operativo:', false);
        $response->assertSee('AM', false);
    }

    public function test_operating_hours_hint_follows_system_time_format_in_24h(): void
    {
        app(SystemSettings::class)->saveFields('system', ['system_time_format' => '24h']);

        $response = $this->actingAs($this->admin())->get(route('pets.bookings.create', $this->pet()));

        $response->assertOk();
        $response->assertSee('Horario operativo:', false);

        preg_match('/Horario operativo:.*?\.<\/div>/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'No se encontró el hint de horario operativo.');
        $this->assertMatchesRegularExpression('/^Horario operativo: \d{2}:\d{2}–\d{2}:\d{2}\.<\/div>$/', $matches[0]);
    }

    public function test_blocked_operator_window_in_agenda_index_follows_system_time_format(): void
    {
        app(SystemSettings::class)->saveFields('system', ['system_time_format' => '12h']);

        $operator = Operator::create(['code' => 'OP2'.uniqid(), 'name' => 'Luis', 'first_name' => 'Luis', 'is_active' => true]);
        $operator->unavailabilities()->create([
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 13:00:00',
            'reason' => 'Vacaciones',
        ]);

        $response = $this->actingAs($this->admin())->get('/agenda?date=2026-07-06&date_scope=custom');

        $response->assertOk();
        $response->assertSee('9:00 AM');
        $response->assertSee('1:00 PM');
        $response->assertDontSee('09:00–13:00', false);
    }
}
