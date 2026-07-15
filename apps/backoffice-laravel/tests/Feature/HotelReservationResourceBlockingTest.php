<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\HotelReservation;
use App\Models\Pet;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelReservationResourceBlockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_reservation_can_block_a_cage_for_the_reserved_range(): void
    {
        [$pet, $resource] = $this->makePetAndResource('J-H01');

        $createResponse = $this->get(route('hotel-reservations.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('Jaula / recurso de hospedaje');
        $createResponse->assertSee('La jaula queda bloqueada durante todo el rango reservado');

        $response = $this->post(route('hotel-reservations.store'), [
            'pet_id' => $pet->id,
            'resource_id' => $resource->id,
            'start_at' => '2026-03-28 09:00:00',
            'end_at' => '2026-03-29 18:00:00',
        ]);

        $reservation = HotelReservation::firstOrFail();

        $response->assertRedirect(route('hotel-reservations.show', $reservation));
        $this->assertDatabaseHas('hotel_reservations', [
            'id' => $reservation->id,
            'pet_id' => $pet->id,
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('resource_allocations', [
            'resource_id' => $resource->id,
            'source_type' => $reservation->getMorphClass(),
            'source_id' => $reservation->id,
            'allocation_type' => 'reserved',
            'pet_id' => $pet->id,
            'starts_at' => '2026-03-28 09:00:00',
            'ends_at' => '2026-03-29 18:00:00',
        ]);
        $this->assertSame(1, $reservation->resourceAllocations()->count());

        $showResponse = $this->get(route('hotel-reservations.show', $reservation));
        $showResponse->assertOk();
        $showResponse->assertSee('Jaula bloqueada');
        $showResponse->assertSee($resource->code);
    }

    public function test_hotel_reservation_rejects_overlapping_cage_block(): void
    {
        [$pet, $resource] = $this->makePetAndResource('J-H02');
        $secondPet = $this->makePet('milo@example.com', 'Milo');

        $this->post(route('hotel-reservations.store'), [
            'pet_id' => $pet->id,
            'resource_id' => $resource->id,
            'start_at' => '2026-03-28 09:00:00',
            'end_at' => '2026-03-29 18:00:00',
        ])->assertRedirect();

        $response = $this->from(route('hotel-reservations.create'))->post(route('hotel-reservations.store'), [
            'pet_id' => $secondPet->id,
            'resource_id' => $resource->id,
            'start_at' => '2026-03-29 10:00:00',
            'end_at' => '2026-03-30 10:00:00',
        ]);

        $response->assertRedirect(route('hotel-reservations.create'));
        $response->assertSessionHasErrors('resource_id');
        $this->assertCount(1, HotelReservation::all());
    }

    public function test_cancelling_hotel_reservation_releases_the_cage_block(): void
    {
        [$pet, $resource] = $this->makePetAndResource('J-H03');

        $this->post(route('hotel-reservations.store'), [
            'pet_id' => $pet->id,
            'resource_id' => $resource->id,
            'start_at' => '2026-03-28 09:00:00',
            'end_at' => '2026-03-29 18:00:00',
        ])->assertRedirect();

        $reservation = HotelReservation::firstOrFail();

        $response = $this->post(route('hotel-reservations.cancel', $reservation));

        $response->assertRedirect(route('hotel-reservations.show', $reservation));
        $this->assertDatabaseHas('hotel_reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseMissing('resource_allocations', [
            'source_type' => $reservation->getMorphClass(),
            'source_id' => $reservation->id,
        ]);
    }

    private function makePetAndResource(string $code): array
    {
        $pet = $this->makePet('ana@example.com', 'Nina');
        $branch = Branch::create([
            'code' => 'MTY-CEN',
            'name' => 'Monterrey Centro',
            'is_active' => true,
        ]);
        $resource = Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => $code,
            'name' => 'Jaula ' . $code,
            'capacity_label' => 'Mediana',
            'administrative_status' => 'active',
            'operational_status' => 'available',
        ]);

        return [$pet, $resource];
    }

    private function makePet(string $email, string $petName): Pet
    {
        $client = Client::create([
            'first_name' => 'Ana',
            'apellido_paterno' => 'Lopez',
            'email' => $email,
        ]);

        return Pet::create([
            'client_id' => $client->id,
            'name' => $petName,
            'species' => 'perro',
        ]);
    }
}