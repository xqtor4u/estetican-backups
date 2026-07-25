<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetDeactivateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'apellido_paterno' => 'Test',
            'email' => 'admin-pet-deactivate-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
            'can_login' => true,
        ]);
    }

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    public function test_destroy_marks_the_pet_inactive_instead_of_deleting_it(): void
    {
        $pet = $this->pet();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->delete(route('pets.destroy', $pet));

        $response->assertRedirect(route('pets.index', ['view' => 'blocks']));
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'is_active' => false]);
        $this->assertNotSoftDeleted($pet);
    }

    public function test_destroy_is_blocked_while_the_pet_has_active_bookings(): void
    {
        $pet = $this->pet();
        $admin = $this->admin();

        SpaBooking::create([
            'pet_id' => $pet->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
            'total_estimated_price' => 0,
        ]);

        $response = $this->actingAs($admin)->delete(route('pets.destroy', $pet));

        $response->assertRedirect();
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'is_active' => true]);
    }

    public function test_destroy_from_client_also_deactivates_instead_of_deleting(): void
    {
        $pet = $this->pet();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->delete(route('clients.pets.destroy', [$pet->client, $pet]));

        $response->assertRedirect(route('clients.show', $pet->client));
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'is_active' => false]);
        $this->assertNotSoftDeleted($pet);
    }

    public function test_index_status_filter_inactive_shows_only_inactive_pets(): void
    {
        $activePet = $this->pet();
        $activePet->update(['name' => 'Rocco']);
        $inactivePet = $this->pet();
        $inactivePet->update(['name' => 'Nala', 'is_active' => false]);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('pets.index', ['status' => 'inactive']));

        $response->assertOk();
        $response->assertSee('Nala');
        $response->assertDontSee('Rocco');
    }

    public function test_editing_a_pet_can_reactivate_it(): void
    {
        $pet = $this->pet();
        $pet->update(['is_active' => false]);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->put(route('pets.update', $pet), [
            'name' => $pet->name,
            'is_active' => '1',
            'return_view_mode' => 'blocks',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'is_active' => true]);
    }
}
