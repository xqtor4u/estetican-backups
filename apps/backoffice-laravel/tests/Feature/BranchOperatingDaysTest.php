<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Support\SystemSettings\BusinessHours;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * ZEUS-047 (07/10/2026): días de operación. Generales en Configuración (`booking_open_*`, todos
 * abiertos por omisión) y propios por sucursal (`branches.operating_days`). Un día cerrado se
 * rechaza al agendar (web y móvil) y el "próximo hueco" lo salta.
 */
class BranchOperatingDaysTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-07 08:00:00')); // miércoles
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function closeSundaysGenerally(): void
    {
        app(SystemSettings::class)->saveFields('operations', ['booking_open_sunday' => false]);
    }

    private function bookingPayload(string $dateTime): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'OD-S'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30, 'open_to_all_operators' => true]);
        $operator = Operator::create(['code' => 'OP-OD'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        return [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $dateTime,
            'services' => [['id' => $service->id, 'operator_id' => $operator->id]],
        ];
    }

    public function test_all_days_are_open_until_configured(): void
    {
        $hours = app(BusinessHours::class);

        $this->assertSame([1, 2, 3, 4, 5, 6, 0], $hours->operatingDays());
        $this->assertSame('todos los días', $hours->operatingDaysLabel());
        $this->assertTrue($hours->isWithin(Carbon::parse('2026-10-11 10:00'))); // domingo
    }

    public function test_general_closed_day_and_branch_own_days(): void
    {
        $this->closeSundaysGenerally();
        $centro = Branch::create(['code' => 'OD-C', 'name' => 'Centro', 'is_active' => true, 'operating_days' => '2,3,4,5,6']);
        $norte = Branch::create(['code' => 'OD-N', 'name' => 'Norte', 'is_active' => true]);
        $hours = app(BusinessHours::class);

        $this->assertSame('lunes a sábado', $hours->for($norte->id)->operatingDaysLabel());
        $this->assertSame('martes a sábado', $hours->for($centro->id)->operatingDaysLabel());
        $this->assertFalse($hours->for($centro->id)->isOpenOn(Carbon::parse('2026-10-12'))); // lunes
        $this->assertTrue($hours->for($norte->id)->isOpenOn(Carbon::parse('2026-10-12')));
        $this->assertSame('El negocio no abre los domingos.', $hours->for($norte->id)->rejectionFor(Carbon::parse('2026-10-11 10:00')));
    }

    public function test_mobile_and_web_booking_on_a_closed_day_is_rejected(): void
    {
        $this->closeSundaysGenerally();
        $admin = $this->createAdminUser();

        $this->withHeaders($this->createAdminAuthHeader($admin))
            ->postJson('/api/bookings', $this->bookingPayload('2026-10-11 10:00:00'))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'El negocio no abre los domingos.']);

        $payload = $this->bookingPayload('2026-10-11 10:00:00');
        $this->actingAs($admin)->post(route('pets.bookings.store', $payload['pet_id']), [
            'scheduled_at' => '2026-10-11 10:00:00',
            'services' => [$payload['services'][0]['id']],
            'service_operators' => [$payload['services'][0]['id'] => $payload['operator_id']],
        ])->assertSessionHas('error', 'El negocio no abre los domingos.');

        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_next_slot_skips_closed_days(): void
    {
        $this->closeSundaysGenerally();
        $operator = Operator::create(['code' => 'OP-NS', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        $this->actingAs($this->createAdminUser())
            ->getJson(route('agenda.next-slot', ['operator_id' => $operator->id, 'duration_minutes' => 60, 'from' => '2026-10-11']))
            ->assertOk()
            ->assertJson(['found' => true, 'date' => '2026-10-12', 'time' => '09:00']);
    }

    public function test_branch_form_saves_own_days_or_falls_back_to_general(): void
    {
        $branch = Branch::create(['code' => 'OD-F', 'name' => 'Centro', 'is_active' => true]);
        $admin = $this->createAdminUser();
        $base = ['code' => 'OD-F', 'name' => 'Centro', 'is_active' => 1];

        $this->actingAs($admin)->get(route('branches.create'))->assertOk()->assertSee('Días de operación propios');
        $this->get(route('branches.edit', $branch))->assertOk()->assertSee('Usa los días generales');

        $this->actingAs($admin)->put(route('branches.update', $branch), $base + ['own_operating_days' => 1])
            ->assertSessionHasErrors('operating_days');

        $this->actingAs($admin)->put(route('branches.update', $branch), $base + ['own_operating_days' => 1, 'operating_days' => [6, 1, 2]])
            ->assertSessionHasNoErrors();
        $this->assertSame('1,2,6', $branch->fresh()->operating_days);

        $this->get(route('branches.show', $branch))->assertOk()->assertSee('Lunes, martes y sábado');

        $this->actingAs($admin)->put(route('branches.update', $branch), $base + ['own_operating_days' => 0, 'operating_days' => [1]])
            ->assertSessionHasNoErrors();
        $this->assertNull($branch->fresh()->operating_days, 'apagado = días generales');
    }
}
