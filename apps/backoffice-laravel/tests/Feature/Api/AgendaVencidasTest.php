<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaVencidasTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(): array
    {
        $user = User::create([
            'name' => 'Operador Vencidas',
            'first_name' => 'Operador',
            'apellido_paterno' => 'Vencidas',
            'email' => 'operador-vencidas-test-'.uniqid().'@example.com',
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

    private function booking(string $status, Carbon $scheduledAt, ?int $durationMinutes = 30): SpaBooking
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz'.uniqid()]);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Mascota-'.uniqid()]);

        return SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => $scheduledAt,
            'status' => $status,
            'duration_minutes' => $durationMinutes,
            'total_estimated_price' => 100,
        ]);
    }

    public function test_flags_a_work_order_still_open_well_past_its_expected_duration_today(): void
    {
        $overdue = $this->booking('work_order', now()->subHours(3), 30);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($overdue->id));
        $this->assertSame('overdue', collect($response->json())->firstWhere('id', $overdue->id)['reason']);
    }

    public function test_flags_a_work_order_scheduled_in_the_future(): void
    {
        $future = $this->booking('work_order', now()->addHours(3), 30);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($future->id));
        $this->assertSame('future', collect($response->json())->firstWhere('id', $future->id)['reason']);
    }

    public function test_still_flags_bookings_left_open_from_previous_days(): void
    {
        $stale = $this->booking('scheduled', now()->subDays(2), 30);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($stale->id));
        $this->assertSame('stale_day', collect($response->json())->firstWhere('id', $stale->id)['reason']);
    }

    public function test_flags_a_scheduled_booking_hours_late_today_that_was_never_started(): void
    {
        $late = $this->booking('scheduled', now()->subHours(2), 30);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $row = collect($response->json())->firstWhere('id', $late->id);
        $this->assertNotNull($row);
        $this->assertSame('not_started', $row['reason']);
    }

    public function test_does_not_flag_a_scheduled_booking_within_the_grace_period(): void
    {
        $onTime = $this->booking('scheduled', now()->subMinutes(5), 30);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($onTime->id));
    }

    public function test_does_not_flag_a_work_order_still_within_its_expected_duration(): void
    {
        $onTime = $this->booking('work_order', now()->subMinutes(10), 30);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($onTime->id));
    }

    public function test_does_not_flag_a_scheduled_booking_later_today(): void
    {
        $laterToday = $this->booking('scheduled', now()->addHours(2), 30);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($laterToday->id));
    }

    public function test_does_not_flag_closed_statuses_regardless_of_date(): void
    {
        $cancelled = $this->booking('cancelled', now()->subDays(2), 30);
        $noShow = $this->booking('no_show', now()->subDays(2), 30);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($cancelled->id));
        $this->assertFalse($ids->contains($noShow->id));
    }

    public function test_flags_a_completed_booking_with_an_outstanding_balance(): void
    {
        $booking = $this->booking('completed', now()->subHours(2), 30);
        $booking->update(['total_estimated_price' => 250]);
        Payment::create([
            'client_id' => $booking->pet->client_id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => 100,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
            'category' => 'liquidacion',
        ]);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $row = collect($response->json())->firstWhere('id', $booking->id);
        $this->assertNotNull($row);
        $this->assertSame('pending_balance', $row['reason']);
        $this->assertEquals(150.0, (float) $row['balance']);
    }

    public function test_does_not_flag_a_completed_booking_that_was_fully_paid(): void
    {
        $booking = $this->booking('completed', now()->subHours(2), 30);
        $booking->update(['total_estimated_price' => 250]);
        Payment::create([
            'client_id' => $booking->pet->client_id,
            'payable_type' => SpaBooking::class,
            'payable_id' => $booking->id,
            'amount' => 250,
            'payment_method' => 'Efectivo',
            'destination' => 'caja',
            'category' => 'liquidacion',
        ]);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($booking->id));
    }

    public function test_does_not_flag_a_completed_booking_with_no_charge_at_all(): void
    {
        $booking = $this->booking('completed', now()->subDays(2), 30);
        $booking->update(['total_estimated_price' => 0]);

        $response = $this->withHeaders($this->authHeader())->getJson('/api/agenda/vencidas');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($booking->id));
    }
}
