<?php

namespace Tests\Feature\Series;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use Database\Seeders\BaseRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/** ZEUS-047 — alta de serie recurrente desde el móvil (`POST /api/bookings` con repeat_*). */
class CreateSeriesFromMobileTest extends TestCase
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

    private function payload(array $repeat = []): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $service = Service::create(['code' => 'BC'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 250, 'duration_minutes' => 60, 'open_to_all_operators' => true]);

        return [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => '2026-10-14 11:00:00',
            'services' => [['id' => $service->id, 'operator_id' => $operator->id, 'price' => 300]],
            ...$repeat,
        ];
    }

    public function test_mobile_creates_the_series_and_returns_a_summary(): void
    {
        $admin = $this->createAdminUser();

        $this->withHeaders($this->createAdminAuthHeader($admin))
            ->postJson('/api/bookings', $this->payload(['repeat_enabled' => true, 'repeat_mode' => '15', 'repeat_until' => 'date', 'repeat_until_date' => '2026-11-15']))
            ->assertCreated()
            ->assertJsonPath('series.rule_label', 'Cada 15 días')
            ->assertJsonPath('series.created', 3)
            ->assertJsonPath('series.bookings.1.scheduled_at', '2026-10-29 11:00:00');

        $this->assertSame(3, SpaBooking::whereNotNull('series_id')->whereNull('series_confirmed_at')->count());
        $this->assertSame(300.0, (float) SpaBooking::first()->services->first()->current_price);
    }

    public function test_user_flag_and_permission(): void
    {
        $this->withHeaders($this->createAdminAuthHeader($this->createAdminUser()))
            ->getJson('/api/me')
            ->assertJsonPath('can_create_series', true);

        (new BaseRolesSeeder)->run();
        $role = Role::create(['name' => 'op-sin-series', 'guard_name' => 'web']);
        $role->givePermissionTo(['ver agenda', 'crear agenda']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->withHeaders($this->createAdminAuthHeader($user))
            ->postJson('/api/bookings', $this->payload(['repeat_enabled' => true, 'repeat_mode' => '7', 'repeat_until' => '6m']))
            ->assertForbidden();

        $this->assertDatabaseCount('spa_bookings', 0);
    }

    public function test_mobile_detail_shows_the_series_and_pins_the_booking(): void
    {
        $headers = $this->createAdminAuthHeader($this->createAdminUser());
        $this->withHeaders($headers)
            ->postJson('/api/bookings', $this->payload(['repeat_enabled' => true, 'repeat_mode' => '30', 'repeat_until' => '6m']))
            ->assertCreated();
        $booking = SpaBooking::orderBy('scheduled_at')->first();

        $this->withHeaders($headers)->getJson('/api/bookings/'.$booking->id)
            ->assertOk()
            ->assertJsonPath('series.rule_label', 'Cada 30 días')
            ->assertJsonPath('series.tentative', true);

        $this->withHeaders($headers)->postJson('/api/bookings/'.$booking->id.'/fijar')
            ->assertOk()
            ->assertJsonPath('series.tentative', false);
        $this->assertNotNull($booking->fresh()->series_confirmed_at);

        $this->withHeaders($headers)->postJson('/api/bookings/'.$booking->id.'/fijar')->assertStatus(422);
    }
}
