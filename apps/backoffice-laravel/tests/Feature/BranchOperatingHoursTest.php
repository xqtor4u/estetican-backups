<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\SystemSettings\BusinessHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * B2 (27/09/2026): horario operativo por sucursal. Sin horario propio, la sucursal usa el general
 * de Configuración (09:00–19:00 por omisión); con él, agendar fuera de ese horario se rechaza.
 */
class BranchOperatingHoursTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    /** Sucursal "Centro" con horario corto propio + otra activa sin horario propio. */
    private function branches(): array
    {
        $centro = Branch::create(['code' => 'B2-C', 'name' => 'Centro', 'is_active' => true, 'opening_time' => '12:00', 'closing_time' => '16:00']);
        $norte = Branch::create(['code' => 'B2-N', 'name' => 'Norte', 'is_active' => true]);

        return [$centro, $norte];
    }

    private function adminIn(?Branch $branch): User
    {
        return $this->createAdminUser(['branch_id' => $branch?->id]);
    }

    private function bookingPayload(string $time): array
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $service = Service::create(['code' => 'B2-S'.uniqid(), 'name' => 'Baño', 'type' => 'spa', 'price' => 100, 'duration_minutes' => 30, 'open_to_all_operators' => true]);
        $operator = Operator::create(['code' => 'OP-B2'.uniqid(), 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);

        return [
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d').' '.$time.':00',
            'services' => [['id' => $service->id, 'operator_id' => $operator->id]],
            'override_availability' => true,
        ];
    }

    public function test_a_branch_uses_its_own_hours_and_otherwise_the_general_ones(): void
    {
        [$centro, $norte] = $this->branches();
        $hours = app(BusinessHours::class);

        $this->assertSame(['12:00', '16:00'], [$hours->for($centro->id)->openingTime(), $hours->for($centro->id)->closingTime()]);
        $this->assertSame(['09:00', '19:00'], [$hours->for($norte->id)->openingTime(), $hours->for($norte->id)->closingTime()]);
        $this->assertSame(['09:00', '19:00'], [$hours->openingTime(), $hours->closingTime()], 'sin for(), el general');
    }

    public function test_mobile_booking_outside_the_branch_hours_is_rejected_even_if_inside_the_general_ones(): void
    {
        [$centro] = $this->branches();
        $headers = $this->createAdminAuthHeader($this->adminIn($centro));

        $this->withHeaders($headers)->postJson('/api/bookings', $this->bookingPayload('10:00'))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'La hora elegida está fuera del horario operativo (12:00–16:00).']);

        $this->withHeaders($headers)->postJson('/api/bookings', $this->bookingPayload('13:00'))->assertCreated();
    }

    public function test_a_branch_without_own_hours_keeps_the_general_hours(): void
    {
        [, $norte] = $this->branches();
        $headers = $this->createAdminAuthHeader($this->adminIn($norte));

        $this->withHeaders($headers)->postJson('/api/bookings', $this->bookingPayload('10:00'))->assertCreated();
    }

    public function test_rescheduling_uses_the_hours_of_the_booking_branch(): void
    {
        [$centro] = $this->branches();
        $admin = $this->adminIn(null);
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = Operator::create(['code' => 'OP-B2R', 'name' => 'Jose', 'first_name' => 'Jose', 'is_active' => true]);
        $booking = SpaBooking::create(['pet_id' => $pet->id, 'operator_id' => $operator->id, 'branch_id' => $centro->id, 'scheduled_at' => now()->addDay()->setTime(13, 0), 'status' => 'scheduled', 'duration_minutes' => 30, 'total_estimated_price' => 100]);

        $this->withHeaders($this->createAdminAuthHeader($admin))
            ->patchJson('/api/bookings/'.$booking->id, ['scheduled_at' => now()->addDay()->format('Y-m-d').' 10:00:00'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'La hora elegida está fuera del horario operativo (12:00–16:00).']);
    }

    public function test_mobile_slot_grid_receives_the_hours_of_the_users_branch(): void
    {
        [$centro] = $this->branches();

        $this->withHeaders($this->createAdminAuthHeader($this->adminIn($centro)))
            ->getJson('/api/settings/booking')
            ->assertOk()
            ->assertJson(['opening_time' => '12:00', 'closing_time' => '16:00']);
    }

    public function test_branch_form_saves_hours_both_or_none_and_closing_after_opening(): void
    {
        [$centro] = $this->branches();
        $admin = $this->adminIn(null);
        $base = ['code' => 'B2-C', 'name' => 'Centro', 'is_active' => 1];

        $this->actingAs($admin)->put(route('branches.update', $centro), $base + ['opening_time' => '12:00'])
            ->assertSessionHasErrors('closing_time');
        $this->actingAs($admin)->put(route('branches.update', $centro), $base + ['opening_time' => '16:00', 'closing_time' => '12:00'])
            ->assertSessionHasErrors('closing_time');

        $this->actingAs($admin)->put(route('branches.update', $centro), $base + ['opening_time' => '08:00', 'closing_time' => '20:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['08:00', '20:00'], [$centro->fresh()->opening_time, $centro->fresh()->closing_time]);

        $this->actingAs($admin)->put(route('branches.update', $centro), $base)->assertSessionHasNoErrors();
        $this->assertNull($centro->fresh()->opening_time, 'vacío = vuelve al horario general');
    }
}
