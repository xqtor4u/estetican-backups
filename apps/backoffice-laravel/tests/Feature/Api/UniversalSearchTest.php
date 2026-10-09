<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Phone;
use App\Models\SpaBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\Concerns\CreatesRestrictedOperatorUser;
use Tests\TestCase;

/**
 * ZEUS-032 (Fase 5 "Mínimo de Clicks Posible"): búsqueda universal —
 * `Api\SearchController::search()` combina pets/clients/spa_bookings en un solo request,
 * reusando `TokenSearch::apply()` tal cual ya la usan `Api\PetController`/`Api\ClientController`.
 */
class UniversalSearchTest extends TestCase
{
    use CreatesAdminUser;
    use CreatesRestrictedOperatorUser;
    use RefreshDatabase;

    private function petWithClient(string $petName, string $clientFirstName, ?string $phone = null): array
    {
        $client = Client::create(['first_name' => $clientFirstName, 'apellido_paterno' => 'Ruiz'.uniqid()]);
        if ($phone) {
            Phone::create(['client_id' => $client->id, 'number' => $phone, 'type' => 'movil', 'sort_order' => 1]);
        }
        $pet = Pet::create(['client_id' => $client->id, 'name' => $petName]);

        return [$pet, $client];
    }

    public function test_finds_a_pet_by_name(): void
    {
        $admin = $this->createAdminUser();
        [$pet] = $this->petWithClient('Luka', 'Ana');

        $response = $this->withHeaders($this->createAdminAuthHeader($admin))->getJson('/api/search?q=Luka');

        $response->assertOk();
        $this->assertTrue(collect($response->json('pets'))->pluck('id')->contains($pet->id));
    }

    public function test_finds_a_pet_client_and_booking_by_phone(): void
    {
        $admin = $this->createAdminUser();
        [$pet, $client] = $this->petWithClient('Firu', 'Carla', '5599998888');
        $booking = SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'duration_minutes' => 30,
            'total_estimated_price' => 100,
        ]);

        $response = $this->withHeaders($this->createAdminAuthHeader($admin))->getJson('/api/search?q=5599998888');

        $response->assertOk();
        $this->assertTrue(collect($response->json('pets'))->pluck('id')->contains($pet->id));
        $this->assertTrue(collect($response->json('clients'))->pluck('id')->contains($client->id));
        $this->assertTrue(collect($response->json('bookings'))->pluck('id')->contains($booking->id));
    }

    public function test_a_restricted_operator_only_sees_their_own_bookings(): void
    {
        $mine = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Mia', 'first_name' => 'Mia', 'is_active' => true]);
        $other = Operator::create(['code' => 'OP'.uniqid(), 'name' => 'Otro', 'first_name' => 'Otro', 'is_active' => true]);
        $user = $this->createOperatorUser(['ver agenda'], $mine);

        [$pet] = $this->petWithClient('Firu', 'Carla');
        $myBooking = SpaBooking::create([
            'pet_id' => $pet->id, 'operator_id' => $mine->id, 'scheduled_at' => now()->addDay(),
            'status' => 'scheduled', 'duration_minutes' => 30, 'total_estimated_price' => 100,
        ]);
        $otherBooking = SpaBooking::create([
            'pet_id' => $pet->id, 'operator_id' => $other->id, 'scheduled_at' => now()->addDay(),
            'status' => 'scheduled', 'duration_minutes' => 30, 'total_estimated_price' => 100,
        ]);

        $response = $this->withHeaders($this->operatorAuthHeader($user))->getJson('/api/search?q=Firu');

        $response->assertOk();
        $bookingIds = collect($response->json('bookings'))->pluck('id');
        $this->assertTrue($bookingIds->contains($myBooking->id));
        $this->assertFalse($bookingIds->contains($otherBooking->id));
    }

    public function test_a_user_without_ver_clientes_gets_an_empty_clients_group_without_a_403(): void
    {
        $user = $this->createOperatorUser(['ver agenda', 'ver mascotas']);
        $this->petWithClient('Luka', 'Ana');

        $response = $this->withHeaders($this->operatorAuthHeader($user))->getJson('/api/search?q=Ana');

        $response->assertOk();
        $this->assertSame([], $response->json('clients'));
    }

    public function test_caps_each_group_at_five_results(): void
    {
        $admin = $this->createAdminUser();
        for ($i = 0; $i < 7; $i++) {
            $this->petWithClient('Buscable-'.$i, 'Dueño-'.$i);
        }

        $response = $this->withHeaders($this->createAdminAuthHeader($admin))->getJson('/api/search?q=Buscable');

        $response->assertOk();
        $this->assertCount(5, $response->json('pets'));
    }
}
