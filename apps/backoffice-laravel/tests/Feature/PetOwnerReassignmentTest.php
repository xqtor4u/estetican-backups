<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetOwnerReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin-pet-owner-test@example.com',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    public function test_pet_show_page_renders_the_change_owner_button(): void
    {
        $owner = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $otherClient = Client::create(['first_name' => 'Beto', 'last_name' => 'Soto']);
        $pet = Pet::create(['client_id' => $owner->id, 'name' => 'Luka']);

        $this->actingAs($this->admin())
            ->get(route('pets.show', $pet))
            ->assertOk()
            ->assertSee('Cambiar dueño')
            ->assertSee('Beto Soto', false);
    }

    public function test_reassigns_pet_to_a_different_client(): void
    {
        $originalOwner = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $newOwner = Client::create(['first_name' => 'Beto', 'last_name' => 'Soto']);
        $pet = Pet::create(['client_id' => $originalOwner->id, 'name' => 'Luka']);

        $response = $this->actingAs($this->admin())
            ->put(route('pets.owner.update', $pet), ['client_id' => $newOwner->id]);

        $response->assertRedirect(route('pets.show', $pet));
        $this->assertSame($newOwner->id, $pet->fresh()->client_id);
    }

    public function test_rejects_a_client_id_that_does_not_exist(): void
    {
        $owner = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $owner->id, 'name' => 'Luka']);

        $response = $this->actingAs($this->admin())
            ->put(route('pets.owner.update', $pet), ['client_id' => 999999]);

        $response->assertSessionHasErrors('client_id');
        $this->assertSame($owner->id, $pet->fresh()->client_id);
    }

    public function test_keeps_owner_when_the_same_client_is_submitted(): void
    {
        $owner = Client::create(['first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $owner->id, 'name' => 'Luka']);

        $response = $this->actingAs($this->admin())
            ->put(route('pets.owner.update', $pet), ['client_id' => $owner->id]);

        $response->assertRedirect(route('pets.show', $pet));
        $this->assertSame($owner->id, $pet->fresh()->client_id);
    }
}
