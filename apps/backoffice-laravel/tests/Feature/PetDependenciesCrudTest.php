<?php

namespace Tests\Feature;

use App\Support\PetPhotoImageManager;
use App\Models\Client;
use App\Models\Pet;
use App\Models\PetMedicalAlert;
use App\Models\PetPhoto;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PetDependenciesCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_pet_management_page_allows_selecting_other_pet_from_same_client(): void
    {
        $client = Client::create([
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'email' => 'ana@example.com',
        ]);

        $selectedPet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Nina',
            'species' => 'Perro',
        ]);

        $otherPet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Milo',
            'species' => 'Gato',
        ]);

        $response = $this->get(route('clients.pets.show', [$client, $selectedPet]));

        $response->assertOk();
        $response->assertSee('Mascota seleccionada');
        $response->assertSee('Nina');
        $response->assertSee('Milo');
    }

    public function test_nested_pet_detail_allows_updating_core_pet_data(): void
    {
        $client = Client::create([
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'email' => 'ana-core@example.com',
        ]);

        $pet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Nina',
            'species' => 'perro',
        ]);

        $response = $this->put(route('clients.pets.update', [$client, $pet]), [
            'name' => 'Nina Editada',
            'species' => 'perro',
            'breed' => 'French Poodle',
            'birth_date' => '',
            'death_date' => '',
            'microchip_code' => '',
            'tattoo_code' => '',
            'sex' => 'female',
            'coat_color' => 'Blanco',
            'size' => 'small',
            'is_sterilized' => '1',
            'notes' => 'Actualizada desde vista por cliente',
        ]);

        $response->assertRedirect(route('clients.pets.show', [$client, $pet]) . '#core-profile');

        $this->assertDatabaseHas('pets', [
            'id' => $pet->id,
            'name' => 'Nina Editada',
            'breed' => 'French Poodle',
            'sex' => 'female',
        ]);
    }

    public function test_can_crud_medical_alerts_and_photos_for_selected_pet(): void
    {
        Storage::fake('public');

        $client = Client::create([
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'email' => 'ana2@example.com',
        ]);

        $pet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Nina',
        ]);

        $this->post(route('clients.pets.medical-alerts.store', [$client, $pet]), [
            'category' => 'Alergia',
            'description' => 'Alergia a pollo',
            'severity' => 'high',
            'notes' => 'Evitar alimento con pollo',
            'is_active' => '1',
        ])->assertRedirect(route('clients.pets.show', [$client, $pet]) . '#medical-alerts');

        $medicalAlert = PetMedicalAlert::firstOrFail();

        $this->assertDatabaseHas('pet_medical_alerts', [
            'id' => $medicalAlert->id,
            'pet_id' => $pet->id,
            'category' => 'Alergia',
            'is_active' => true,
        ]);

        $this->put(route('clients.pets.medical-alerts.update', [$client, $pet, $medicalAlert]), [
            'category' => 'Alergia alimentaria',
            'description' => 'Alergia a pollo y res',
            'severity' => 'critical',
            'notes' => 'Requiere observacion',
            'is_active' => '0',
        ])->assertRedirect(route('clients.pets.show', [$client, $pet]) . '#medical-alerts');

        $this->assertDatabaseHas('pet_medical_alerts', [
            'id' => $medicalAlert->id,
            'category' => 'Alergia alimentaria',
            'is_active' => false,
        ]);

        $this->post(route('clients.pets.photos.store', [$client, $pet]), [
            'photo' => UploadedFile::fake()->image('nina-1.jpg', 4200, 2800),
            'photo_type' => 'perfil',
            'taken_at' => '2026-03-21 10:00:00',
            'description' => 'Foto de frente',
            'is_primary' => '1',
        ])->assertRedirect(route('clients.pets.show', [$client, $pet]) . '#pet-photos');

        $firstPhoto = PetPhoto::firstOrFail();
        Storage::disk('public')->assertExists($firstPhoto->photo_url);
        Storage::disk('public')->assertExists($firstPhoto->thumbnail_storage_path);

        [$firstWidth, $firstHeight] = getimagesize(Storage::disk('public')->path($firstPhoto->photo_url));
        [$firstThumbWidth, $firstThumbHeight] = getimagesize(Storage::disk('public')->path($firstPhoto->thumbnail_storage_path));

        $this->assertLessThanOrEqual(1600, max($firstWidth, $firstHeight));
        $this->assertSame(160, $firstThumbWidth);
        $this->assertSame(120, $firstThumbHeight);

        $this->post(route('clients.pets.photos.store', [$client, $pet]), [
            'photo' => UploadedFile::fake()->image('nina-2.jpg', 3600, 3600),
            'photo_type' => 'bath',
            'taken_at' => '2026-03-21 11:00:00',
            'description' => 'Despues del bano',
            'is_primary' => '1',
        ])->assertRedirect(route('clients.pets.show', [$client, $pet]) . '#pet-photos');

        $this->assertDatabaseHas('pet_photos', [
            'id' => $firstPhoto->id,
            'is_primary' => false,
        ]);

        $secondPhoto = PetPhoto::orderByDesc('id')->firstOrFail();
        $secondPhotoOriginalPath = $secondPhoto->photo_url;
        $secondPhotoOriginalThumbPath = $secondPhoto->thumbnail_storage_path;

        $this->put(route('clients.pets.photos.update', [$client, $pet, $secondPhoto]), [
            'photo' => UploadedFile::fake()->image('nina-2-updated.jpg', 3000, 2200),
            'photo_type' => 'gallery',
            'taken_at' => '2026-03-21 12:00:00',
            'description' => 'Galeria actualizada',
            'is_primary' => '0',
        ])->assertRedirect(route('clients.pets.show', [$client, $pet]) . '#pet-photos');

        $secondPhoto->refresh();

        $this->assertDatabaseHas('pet_photos', [
            'id' => $secondPhoto->id,
            'photo_type' => 'gallery',
            'is_primary' => false,
        ]);
        Storage::disk('public')->assertMissing($secondPhotoOriginalPath);
        Storage::disk('public')->assertMissing($secondPhotoOriginalThumbPath);
        Storage::disk('public')->assertExists($secondPhoto->photo_url);
        Storage::disk('public')->assertExists($secondPhoto->thumbnail_storage_path);

        [$secondWidth, $secondHeight] = getimagesize(Storage::disk('public')->path($secondPhoto->photo_url));
        [$secondThumbWidth, $secondThumbHeight] = getimagesize(Storage::disk('public')->path($secondPhoto->thumbnail_storage_path));

        $this->assertLessThanOrEqual(1600, max($secondWidth, $secondHeight));
        $this->assertSame(160, $secondThumbWidth);
        $this->assertSame(120, $secondThumbHeight);

        $this->delete(route('clients.pets.medical-alerts.destroy', [$client, $pet, $medicalAlert]))
            ->assertRedirect(route('clients.pets.show', [$client, $pet]) . '#medical-alerts');

        $this->delete(route('clients.pets.photos.destroy', [$client, $pet, $secondPhoto]))
            ->assertRedirect(route('clients.pets.show', [$client, $pet]) . '#pet-photos');

        $this->assertDatabaseMissing('pet_medical_alerts', ['id' => $medicalAlert->id]);
        $this->assertDatabaseMissing('pet_photos', ['id' => $secondPhoto->id]);
        Storage::disk('public')->assertMissing($secondPhoto->photo_url);
        Storage::disk('public')->assertMissing($secondPhoto->thumbnail_storage_path);
    }

    public function test_store_prefills_taken_at_from_photo_metadata_when_field_is_empty(): void
    {
        Storage::fake('public');

        $client = Client::create([
            'first_name' => 'Laura',
            'last_name' => 'Mendez',
            'email' => 'laura@example.com',
        ]);

        $pet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Toby',
        ]);

        $file = UploadedFile::fake()->image('toby.jpg', 2200, 1600);

        $manager = Mockery::mock(PetPhotoImageManager::class);
        $manager->shouldReceive('extractTakenAt')
            ->once()
            ->andReturn(CarbonImmutable::parse('2024-04-05 14:15:16'));
        $manager->shouldReceive('store')
            ->once()
            ->andReturn('pet-photos/2026/03/original/toby.jpg');
        $manager->shouldReceive('deleteFiles')
            ->never();

        $this->app->instance(PetPhotoImageManager::class, $manager);

        $this->post(route('clients.pets.photos.store', [$client, $pet]), [
            'photo' => $file,
            'photo_type' => 'perfil',
            'taken_at' => '',
            'description' => 'Foto con EXIF',
            'is_primary' => '1',
        ])->assertRedirect(route('clients.pets.show', [$client, $pet]) . '#pet-photos');

        $storedPhoto = PetPhoto::firstOrFail();

        $this->assertSame('2024-04-05 14:15:16', $storedPhoto->taken_at?->format('Y-m-d H:i:s'));
    }
}