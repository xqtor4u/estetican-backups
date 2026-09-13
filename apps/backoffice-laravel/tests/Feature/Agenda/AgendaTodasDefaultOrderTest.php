<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Pet;
use App\Models\SpaBooking;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * La ventana "Todas" (date_scope=full) de la vista Día es un historial completo, no una
 * lista de trabajo: al abrirla (sin `direction` explícito en la URL) debe ordenarse de la
 * cita más reciente a la más vieja. El resto de ventanas siguen ascendentes. Un clic en el
 * encabezado "Fecha" navega con `?direction=asc` y ese sí manda.
 */
class AgendaTodasDefaultOrderTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-02 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function booking(string $petName, Carbon $scheduledAt): SpaBooking
    {
        $client = Client::create(['first_name' => 'Cliente', 'apellido_paterno' => 'X'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => $petName]);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'duration_minutes' => 30,
            'total_estimated_price' => 100,
        ]);
    }

    private function seedThreeDates(): void
    {
        $this->booking('MascotaAntigua', now()->subDays(30));
        $this->booking('MascotaReciente', now()->subDay());
        $this->booking('MascotaFutura', now()->addDays(5));
    }

    public function test_todas_opens_with_the_most_recent_booking_first(): void
    {
        $this->seedThreeDates();

        $res = $this->actingAs($this->createAdminUser())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'full']));

        $res->assertOk();
        $res->assertSeeInOrder(['MascotaFutura', 'MascotaReciente', 'MascotaAntigua']);
    }

    public function test_explicit_ascending_direction_overrides_the_todas_default(): void
    {
        $this->seedThreeDates();

        $res = $this->actingAs($this->createAdminUser())
            ->get(route('agenda.index', [
                'status_touched' => 1,
                'date_scope' => 'full',
                'sort' => 'date',
                'direction' => 'asc',
            ]));

        $res->assertOk();
        $res->assertSeeInOrder(['MascotaAntigua', 'MascotaReciente', 'MascotaFutura']);
    }

    public function test_proximas_window_stays_in_ascending_order(): void
    {
        $this->booking('MascotaPronto', now()->addDay());
        $this->booking('MascotaTarde', now()->addDays(10));

        $res = $this->actingAs($this->createAdminUser())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'all']));

        $res->assertOk();
        $res->assertSeeInOrder(['MascotaPronto', 'MascotaTarde']);
    }

    public function test_todas_date_header_link_toggles_to_ascending(): void
    {
        $this->seedThreeDates();

        $res = $this->actingAs($this->createAdminUser())
            ->get(route('agenda.index', ['status_touched' => 1, 'date_scope' => 'full']));

        $res->assertOk();
        // La columna Fecha ya está descendente por defecto → su enlace apunta a asc.
        $res->assertSee('sort=date&amp;direction=asc', false);
    }
}
