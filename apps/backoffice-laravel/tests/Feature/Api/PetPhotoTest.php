<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\Client;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class PetPhotoTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function authHeader(): array
    {
        return $this->createAdminAuthHeader();
    }

    private function makePet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Firulais']);
    }

    public function test_photo_upload_compresses_sets_profile_photo_and_creates_gallery_entry(): void
    {
        Storage::fake('public');
        $pet = $this->makePet();
        $headers = $this->authHeader();

        $response = $this->post("/api/pets/{$pet->id}/photo", [
            'photo' => UploadedFile::fake()->image('firulais.jpg', 2000, 2000),
        ], $headers);

        $response->assertOk();
        $pet->refresh();
        $this->assertNotNull($pet->profile_photo_path);
        $response->assertJsonFragment(['photo' => Storage::disk('public')->url($pet->profile_photo_path)]);

        $this->assertDatabaseHas('pet_photos', [
            'pet_id' => $pet->id,
            'photo_type' => 'perfil',
            'is_primary' => true,
        ]);
    }

    public function test_photo_upload_on_creation_also_creates_gallery_entry(): void
    {
        Storage::fake('public');
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $headers = $this->authHeader();

        $response = $this->post('/api/pets', [
            'client_id' => $client->id,
            'name' => 'Max',
            'photo' => UploadedFile::fake()->image('max.jpg', 1500, 1500),
        ], $headers);

        $response->assertCreated();
        $pet = Pet::findOrFail($response->json('id'));
        $this->assertNotNull($pet->profile_photo_path);
        $this->assertDatabaseHas('pet_photos', ['pet_id' => $pet->id, 'photo_type' => 'perfil']);
    }

    public function test_photo_can_be_removed(): void
    {
        Storage::fake('public');
        $pet = $this->makePet();
        $headers = $this->authHeader();

        $this->post("/api/pets/{$pet->id}/photo", [
            'photo' => UploadedFile::fake()->image('firulais.jpg', 800, 800),
        ], $headers);

        $delete = $this->delete("/api/pets/{$pet->id}/photo", [], $headers);

        $delete->assertOk();
        $delete->assertJson(['photo' => null]);
        $this->assertNull($pet->fresh()->profile_photo_path);
    }
}
