<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Pet;
use App\Models\User;
use App\Support\Images\PhotoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * A4 (27/09/2026): listas y avatares de la API sirven la miniatura (~4 KB) en vez de la foto
 * completa (~240 KB), y el tamaño máximo de subida de fotos es un solo valor configurable.
 */
class PhotoThumbnailsAndUploadLimitTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->createAdminUser();
    }

    /** Mismo camino que Configuración → Fotografías: el valor guardado manda sobre el default. */
    private function setUploadLimit(User $admin, int $mb): void
    {
        $this->actingAs($admin)
            ->patch(route('system-settings.patch-field', 'media'), ['photo_max_upload_mb' => $mb])
            ->assertOk();
    }

    public function test_thumb_url_uses_the_thumbnail_when_it_exists_and_falls_back_otherwise(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pet-photos/2026/09/original/a.jpg', 'x');
        Storage::disk('public')->put('pet-photos/2026/09/thumbs/a.jpg', 'x');
        Storage::disk('public')->put('pet-photos/2026/09/original/b.jpg', 'x');

        $this->assertStringEndsWith('/thumbs/a.jpg', PhotoUrl::thumb('pet-photos/2026/09/original/a.jpg'));
        $this->assertStringEndsWith('/original/b.jpg', PhotoUrl::thumb('pet-photos/2026/09/original/b.jpg'), 'sin miniatura, la principal');
        $this->assertNull(PhotoUrl::thumb(null));
    }

    public function test_mobile_pet_list_serves_thumbnails_but_pet_detail_keeps_the_full_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('pet-photos/2026/09/original/luka.jpg', 'x');
        Storage::disk('public')->put('pet-photos/2026/09/thumbs/luka.jpg', 'x');
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka', 'profile_photo_path' => 'pet-photos/2026/09/original/luka.jpg']);
        $headers = $this->createAdminAuthHeader($this->admin());

        $list = $this->withHeaders($headers)->getJson('/api/pets')->assertOk()->json();
        $detail = $this->withHeaders($headers)->getJson('/api/pets/'.$pet->id)->assertOk()->json();

        $this->assertStringEndsWith('/thumbs/luka.jpg', collect($list)->firstWhere('id', $pet->id)['photo']);
        $this->assertStringEndsWith('/original/luka.jpg', $detail['photo']);
    }

    public function test_upload_limit_is_configurable_and_enforced_by_the_server(): void
    {
        Storage::fake('public');
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $admin = $this->admin();
        $headers = $this->createAdminAuthHeader($admin);

        $this->setUploadLimit($admin, 1);
        $this->withHeaders($headers)
            ->postJson('/api/pets/'.$pet->id.'/photo', ['photo' => UploadedFile::fake()->image('grande.jpg')->size(2048)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');

        $this->setUploadLimit($admin, 3);
        $this->withHeaders($headers)
            ->postJson('/api/pets/'.$pet->id.'/photo', ['photo' => UploadedFile::fake()->image('grande.jpg', 400, 400)->size(2048)])
            ->assertSessionHasNoErrors()
            ->assertJsonMissingValidationErrors('photo');
    }

    public function test_backoffice_pages_expose_the_limit_to_the_upload_widget(): void
    {
        $admin = $this->admin();
        $this->setUploadLimit($admin, 7);

        $this->actingAs($admin)
            ->get(route('user.settings'))
            ->assertOk()
            ->assertSee('<meta name="upload-max-mb" content="7">', false);
    }
}
