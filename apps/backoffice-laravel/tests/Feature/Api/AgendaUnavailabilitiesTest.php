<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaUnavailabilitiesTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(): array
    {
        $user = User::create([
            'name' => 'Operador Test',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Test',
            'email' => 'agenda-unavailabilities-test@example.com',
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

    private function operator(string $name = 'Jose'): Operator
    {
        return Operator::create(['code' => strtoupper(substr($name, 0, 3)).uniqid(), 'name' => $name, 'first_name' => $name, 'is_active' => true]);
    }

    public function test_day_view_returns_windows_overlapping_that_day(): void
    {
        $operator = $this->operator();
        $operator->unavailabilities()->create([
            'starts_at' => '2026-07-06 09:00:00',
            'ends_at' => '2026-07-06 13:00:00',
            'reason' => 'Vacaciones',
        ]);
        $operator->unavailabilities()->create([
            'starts_at' => '2026-07-10 09:00:00',
            'ends_at' => '2026-07-10 13:00:00',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agenda/unavailabilities?date=2026-07-06');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('Vacaciones', $response->json()[0]['reason']);
    }

    public function test_week_view_returns_windows_across_the_whole_week(): void
    {
        $operator = $this->operator();
        $operator->unavailabilities()->create(['starts_at' => '2026-07-06 09:00:00', 'ends_at' => '2026-07-06 13:00:00']);
        $operator->unavailabilities()->create(['starts_at' => '2026-07-13 09:00:00', 'ends_at' => '2026-07-13 13:00:00']);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agenda/unavailabilities?date=2026-07-08&view=week');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_filters_by_operator_id(): void
    {
        $operatorA = $this->operator('Jose');
        $operatorB = $this->operator('Maria');
        $operatorA->unavailabilities()->create(['starts_at' => '2026-07-06 09:00:00', 'ends_at' => '2026-07-06 13:00:00']);
        $operatorB->unavailabilities()->create(['starts_at' => '2026-07-06 09:00:00', 'ends_at' => '2026-07-06 13:00:00']);

        $response = $this->withHeaders($this->authHeader())
            ->getJson("/api/agenda/unavailabilities?date=2026-07-06&operator_id={$operatorA->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame($operatorA->id, $response->json()[0]['operator_id']);
    }

    public function test_returns_empty_when_no_blocks(): void
    {
        $this->operator();

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agenda/unavailabilities?date=2026-07-06');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }
}
