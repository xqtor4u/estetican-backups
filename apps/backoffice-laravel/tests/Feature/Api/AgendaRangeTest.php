<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaRangeTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(): array
    {
        $user = User::create([
            'name' => 'Operador Test',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test',
            'email' => 'agenda-range-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);

        $plainToken = 'test-token-'.uniqid();
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'name' => 'test',
        ]);

        return ['Authorization' => "Bearer {$plainToken}"];
    }

    private function booking(string $scheduledAt): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Op Test', 'first_name' => 'Op Test', 'is_active' => true]);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'duration_minutes' => 60,
            'total_estimated_price' => 0,
        ]);
    }

    public function test_week_view_returns_bookings_across_the_whole_week(): void
    {
        // Lunes 2026-07-06: ancla dentro de la semana lunes-domingo
        $this->booking('2026-07-06 09:00:00'); // lunes, dentro
        $this->booking('2026-07-10 17:00:00'); // viernes, dentro
        $this->booking('2026-07-13 09:00:00'); // lunes siguiente, fuera

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agenda?date=2026-07-08&view=week');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_month_view_returns_bookings_across_the_whole_month(): void
    {
        $this->booking('2026-07-01 09:00:00');
        $this->booking('2026-07-31 18:00:00');
        $this->booking('2026-08-01 09:00:00'); // fuera del mes

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agenda?date=2026-07-15&view=month');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_day_view_is_unchanged_by_default(): void
    {
        $this->booking('2026-07-06 09:00:00');
        $this->booking('2026-07-07 09:00:00');

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agenda?date=2026-07-06');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('2026-07-06', $response->json()[0]['date']);
    }

    public function test_invalid_view_falls_back_to_day(): void
    {
        $this->booking('2026-07-06 09:00:00');
        $this->booking('2026-07-07 09:00:00');

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agenda?date=2026-07-06&view=bogus');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_operators_field_includes_directly_assigned_operator_without_an_accepted_quote(): void
    {
        $booking = $this->booking('2026-07-06 09:00:00');

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agenda?date=2026-07-06');

        $response->assertOk();
        $operators = $response->json()[0]['operators'];

        $this->assertCount(1, $operators);
        $this->assertSame($booking->operator_id, $operators[0]['id']);
    }
}
