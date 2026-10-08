<?php

namespace Tests\Feature\Series;

use App\Models\Client;
use App\Models\NonWorkingDay;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingSeries;
use App\Models\User;
use Database\Seeders\BaseRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * ZEUS-047 — alta de una serie recurrente desde el formulario de cita del backoffice
 * ("Repetir esta cita"): vista previa sin guardar, guardado de la serie completa y permiso propio.
 */
class CreateSeriesFromBackofficeTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private Pet $pet;

    private Operator $operator;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-07 08:00:00')); // miércoles

        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $this->pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $this->operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $this->service = Service::create(['code' => 'BC01', 'name' => 'Baño', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60, 'open_to_all_operators' => true, 'recurrence_days' => 30]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function form(array $repeat): array
    {
        return [
            'scheduled_at' => '2026-10-14 11:00:00',
            'services' => [$this->service->id],
            'service_operators' => [$this->service->id => $this->operator->id],
            'service_durations' => [$this->service->id => 60],
            'notes' => 'Cliente frecuente',
            'repeat_enabled' => 1,
            ...$repeat,
        ];
    }

    public function test_form_shows_the_repeat_section_with_the_service_frequency(): void
    {
        $this->actingAs($this->createAdminUser())
            ->get(route('pets.bookings.create', $this->pet))
            ->assertOk()
            ->assertSee('Repetir esta cita')
            ->assertSee('data-recurrence-days="30"', false)
            ->assertSee('bookings\\/series-preview', false);
    }

    public function test_preview_lists_every_date_and_writes_nothing(): void
    {
        NonWorkingDay::create(['date' => '2026-10-21', 'reason' => 'Festivo']);

        $this->actingAs($this->createAdminUser())
            ->postJson(route('pets.bookings.series-preview', $this->pet), $this->form(['repeat_mode' => '7', 'repeat_until' => 'date', 'repeat_until_date' => '2026-10-28']))
            ->assertOk()
            ->assertJsonPath('rule_label', 'Cada 7 días')
            ->assertJsonCount(3, 'items')
            ->assertJsonPath('items.0.state', 'ok')
            ->assertJsonPath('items.1.state', 'moved')
            ->assertJsonPath('items.1.scheduled_at', '2026-10-22 11:00')
            ->assertJsonPath('items.1.reason', 'Día inhábil: Festivo.');

        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_saving_with_repeat_creates_the_whole_series_to_pin(): void
    {
        $response = $this->actingAs($this->createAdminUser())
            ->post(route('pets.bookings.store', $this->pet), $this->form(['repeat_mode' => 'monthly', 'repeat_until' => '6m']));

        $response->assertRedirect(route('agenda.index', ['por_fijar' => 1]));
        $response->assertSessionHas('success', 'Serie creada (2º miércoles de cada mes): 7 citas. Todas quedan por fijar.');

        $series = SpaBookingSeries::sole();
        $this->assertSame('Cliente frecuente', $series->notes);
        $this->assertSame(
            ['2026-10-14', '2026-11-11', '2026-12-09', '2027-01-13', '2027-02-10', '2027-03-10', '2027-04-14'],
            $series->bookings->map(fn ($b) => $b->scheduled_at->format('Y-m-d'))->all()
        );
        $this->assertTrue($series->bookings->every(fn (SpaBooking $b) => $b->isSeriesTentative()));
        $this->assertSame(7, SpaBooking::count(), 'la primera cita es parte de la serie, no una cita suelta aparte');
    }

    public function test_repeat_requires_its_own_permission(): void
    {
        (new BaseRolesSeeder)->run();
        $role = Role::create(['name' => 'recepcion-sin-series', 'guard_name' => 'web']);
        $role->givePermissionTo(['ver agenda', 'crear agenda']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('pets.bookings.create', $this->pet))->assertOk()->assertDontSee('Repetir esta cita');

        $this->actingAs($user)->post(route('pets.bookings.store', $this->pet), $this->form(['repeat_mode' => '7', 'repeat_until' => '6m']))
            ->assertSessionHas('error', 'No tienes permiso para crear citas recurrentes.');
        $this->actingAs($user)->postJson(route('pets.bookings.series-preview', $this->pet), $this->form(['repeat_mode' => '7', 'repeat_until' => '6m']))
            ->assertForbidden();

        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_without_repeat_it_is_a_normal_booking(): void
    {
        $this->actingAs($this->createAdminUser())
            ->post(route('pets.bookings.store', $this->pet), $this->form(['repeat_enabled' => 0]))
            ->assertRedirect(route('agenda.index'));

        $this->assertDatabaseCount('spa_booking_series', 0);
        $this->assertNull(SpaBooking::sole()->series_id);
    }
}
