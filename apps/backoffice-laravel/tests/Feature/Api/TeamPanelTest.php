<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Operator;
use App\Models\OperatorCheckin;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPanelTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(): array
    {
        $user = User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'team-panel-test@example.com',
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

    private function operatorWithLogin(string $name): array
    {
        $operator = Operator::create([
            'code' => 'OP'.uniqid(),
            'name' => $name,
            'full_name' => $name,
            'is_active' => true,
        ]);

        $loginUser = User::create([
            'name' => $name,
            'first_name' => $name,
            'last_name' => 'Op',
            'email' => strtolower(str_replace(' ', '.', $name)).uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'operator',
            'is_active' => true,
            'can_login' => true,
            'is_operator' => true,
            'operator_id' => $operator->id,
        ]);

        return [$operator, $loginUser];
    }

    public function test_operator_without_checkin_is_reported_as_not_checked_in(): void
    {
        [$operator] = $this->operatorWithLogin('Sin Checkin');

        $response = $this->getJson('/api/team', $this->authHeader());

        $response->assertOk();
        $row = collect($response->json())->firstWhere('id', $operator->id);

        $this->assertFalse($row['checked_in']);
        $this->assertNull($row['current_job']);
        $this->assertSame(0, $row['pending_today']);
        $this->assertSame(0, $row['completed_today']);
    }

    public function test_operator_with_active_checkin_and_open_work_order_reports_current_job(): void
    {
        // Ancla de mediodía: evita que scheduled_at +/- horas cruce medianoche según la hora real de ejecución.
        $this->travelTo(now()->setTime(12, 0));

        [$operator, $loginUser] = $this->operatorWithLogin('Con Servicio');
        $branch = Branch::create(['code' => 'SUC1', 'name' => 'Sucursal Test']);

        OperatorCheckin::create([
            'user_id' => $loginUser->id,
            'branch_id' => $branch->id,
            'checked_in_at' => now()->subHour(),
        ]);

        $client = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Max']);

        SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now(),
            'status' => 'work_order',
            'duration_minutes' => 60,
            'total_estimated_price' => 0,
        ]);

        SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addHours(2),
            'status' => 'scheduled',
            'duration_minutes' => 60,
            'total_estimated_price' => 0,
        ]);

        SpaBooking::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->subHours(3),
            'status' => 'completed',
            'duration_minutes' => 60,
            'total_estimated_price' => 0,
        ]);

        $response = $this->getJson('/api/team', $this->authHeader());

        $response->assertOk();
        $row = collect($response->json())->firstWhere('id', $operator->id);

        $this->assertTrue($row['checked_in']);
        $this->assertSame($branch->id, $row['branch']['id']);
        $this->assertNotNull($row['current_job']);
        $this->assertSame('Max', $row['current_job']['pet_name']);
        $this->assertSame(2, $row['pending_today']); // work_order + scheduled
        $this->assertSame(1, $row['completed_today']);
    }

    public function test_checked_out_checkin_does_not_count_as_active(): void
    {
        [$operator, $loginUser] = $this->operatorWithLogin('Checkout Hecho');
        $branch = Branch::create(['code' => 'SUC2', 'name' => 'Sucursal Test 2']);

        OperatorCheckin::create([
            'user_id' => $loginUser->id,
            'branch_id' => $branch->id,
            'checked_in_at' => now()->subHours(5),
            'checked_out_at' => now()->subHours(1),
        ]);

        $response = $this->getJson('/api/team', $this->authHeader());

        $row = collect($response->json())->firstWhere('id', $operator->id);

        $this->assertFalse($row['checked_in']);
    }
}
