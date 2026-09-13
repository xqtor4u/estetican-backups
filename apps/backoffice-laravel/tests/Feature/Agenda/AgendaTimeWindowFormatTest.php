<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * UX-09 (12/09/2026) — con "Formato de hora" = 12h, la columna FECHA de la agenda ya
 * respetaba el ajuste pero la columna VENTANA (armada en `decorateBooking()` a partir de
 * `time_window_label`) se quedaba hardcodeada en `H:i`. `ApplySystemSettings` ya comparte
 * `timeFormat` con la vista; el fix reusa ese mismo valor en el controller.
 */
class AgendaTimeWindowFormatTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    private function booking(): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $service = Service::create(['code' => 'S'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 200, 'duration_minutes' => 60, 'is_active' => true, 'open_to_all_operators' => true]);

        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->setTime(19, 0),
            'status' => 'scheduled',
            'duration_minutes' => 60,
            'total_estimated_price' => 200,
        ]);
        $booking->services()->create(['service_id' => $service->id, 'current_price' => 200, 'operator_id' => $operator->id]);

        return $booking;
    }

    public function test_the_time_window_column_respects_the_12h_format_setting(): void
    {
        $admin = $this->admin();
        $booking = $this->booking();

        $this->actingAs($admin)
            ->patch(route('system-settings.patch-field', 'system'), ['system_time_format' => '12h'])
            ->assertOk();

        $res = $this->actingAs($admin)->get(route('agenda.index', ['date_scope' => 'today']));

        $res->assertOk();
        $res->assertSee('07:00 PM - 08:00 PM', false);
        $res->assertDontSee('19:00 - 20:00', false);
    }

    public function test_the_time_window_column_still_supports_the_24h_format_setting(): void
    {
        $admin = $this->admin();
        $booking = $this->booking();

        $this->actingAs($admin)
            ->patch(route('system-settings.patch-field', 'system'), ['system_time_format' => '24h'])
            ->assertOk();

        $res = $this->actingAs($admin)->get(route('agenda.index', ['date_scope' => 'today']));

        $res->assertOk();
        $res->assertSee('19:00 - 20:00', false);
    }
}
