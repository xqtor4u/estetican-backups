<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Resource;
use App\Models\ResourcePhoto;
use App\Support\ResourcePhotoImageManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ResourcePhotoCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_can_crud_photos_with_primary_switch_and_file_replacement(): void
    {
        Storage::fake('public');

        $resource = $this->makeResource('J-F01');

        $showResponse = $this->get(route('resources.show', $resource));
        $showResponse->assertOk();
        $showResponse->assertSee('Fotos del recurso');
        $showResponse->assertSee('Nueva foto');

        $this->post(route('resources.photos.store', $resource), [
            'photo' => UploadedFile::fake()->image('jaula-frente.jpg', 4200, 2800),
            'photo_type' => 'evidencia',
            'taken_at' => '2026-03-26 19:00:00',
            'description' => 'Ingreso inicial del activo',
            'is_primary' => '1',
        ])->assertRedirect(route('resources.show', $resource) . '#resource-photos');

        $firstPhoto = ResourcePhoto::firstOrFail();
        Storage::disk('public')->assertExists($firstPhoto->photo_url);
        Storage::disk('public')->assertExists($firstPhoto->thumbnail_storage_path);

        [$firstWidth, $firstHeight] = getimagesize(Storage::disk('public')->path($firstPhoto->photo_url));
        [$firstThumbWidth, $firstThumbHeight] = getimagesize(Storage::disk('public')->path($firstPhoto->thumbnail_storage_path));

        $this->assertLessThanOrEqual(1600, max($firstWidth, $firstHeight));
        $this->assertSame(160, $firstThumbWidth);
        $this->assertSame(120, $firstThumbHeight);

        $this->post(route('resources.photos.store', $resource), [
            'photo' => UploadedFile::fake()->image('jaula-golpe.jpg', 3200, 2200),
            'photo_type' => 'incidente',
            'taken_at' => '2026-03-27 09:15:00',
            'description' => 'Golpe lateral detectado en recepción',
            'is_primary' => '1',
        ])->assertRedirect(route('resources.show', $resource) . '#resource-photos');

        $this->assertDatabaseHas('resource_photos', [
            'id' => $firstPhoto->id,
            'is_primary' => false,
        ]);

        $secondPhoto = ResourcePhoto::orderByDesc('id')->firstOrFail();
        $secondPhotoOriginalPath = $secondPhoto->photo_url;
        $secondPhotoOriginalThumbPath = $secondPhoto->thumbnail_storage_path;

        $this->put(route('resources.photos.update', [$resource, $secondPhoto]), [
            'photo' => UploadedFile::fake()->image('jaula-golpe-reparado.jpg', 3000, 2400),
            'photo_type' => 'seguimiento',
            'taken_at' => '2026-03-28 12:45:00',
            'description' => 'Evidencia tras ajuste y revisión',
            'is_primary' => '0',
        ])->assertRedirect(route('resources.show', $resource) . '#resource-photos');

        $secondPhoto->refresh();

        $this->assertDatabaseHas('resource_photos', [
            'id' => $secondPhoto->id,
            'photo_type' => 'seguimiento',
            'is_primary' => false,
        ]);
        Storage::disk('public')->assertMissing($secondPhotoOriginalPath);
        Storage::disk('public')->assertMissing($secondPhotoOriginalThumbPath);
        Storage::disk('public')->assertExists($secondPhoto->photo_url);
        Storage::disk('public')->assertExists($secondPhoto->thumbnail_storage_path);

        $this->delete(route('resources.photos.destroy', [$resource, $secondPhoto]))
            ->assertRedirect(route('resources.show', $resource) . '#resource-photos');

        $this->assertDatabaseMissing('resource_photos', ['id' => $secondPhoto->id]);
        Storage::disk('public')->assertMissing($secondPhoto->photo_url);
        Storage::disk('public')->assertMissing($secondPhoto->thumbnail_storage_path);
    }

    public function test_store_prefills_taken_at_from_photo_metadata_when_field_is_empty(): void
    {
        $resource = $this->makeResource('J-F02');
        $file = UploadedFile::fake()->image('jaula-exif.jpg', 2200, 1600);

        $manager = Mockery::mock(ResourcePhotoImageManager::class);
        $manager->shouldReceive('extractTakenAt')
            ->once()
            ->andReturn(CarbonImmutable::parse('2024-04-05 14:15:16'));
        $manager->shouldReceive('store')
            ->once()
            ->andReturn('resource-photos/2026/03/original/jaula-exif.jpg');
        $manager->shouldReceive('deleteFiles')
            ->never();

        $this->app->instance(ResourcePhotoImageManager::class, $manager);

        $this->post(route('resources.photos.store', $resource), [
            'photo' => $file,
            'photo_type' => 'evidencia',
            'taken_at' => '',
            'description' => 'Foto con EXIF',
            'is_primary' => '1',
        ])->assertRedirect(route('resources.show', $resource) . '#resource-photos');

        $storedPhoto = ResourcePhoto::firstOrFail();

        $this->assertSame('2024-04-05 14:15:16', $storedPhoto->taken_at?->format('Y-m-d H:i:s'));
        $this->assertSame('resource-photos/2026/03/original/jaula-exif.jpg', $storedPhoto->photo_url);
    }

    private function makeResource(string $code): Resource
    {
        $branch = Branch::create([
            'code' => 'MTY-CEN',
            'name' => 'Monterrey Centro',
            'is_active' => true,
        ]);

        return Resource::create([
            'branch_id' => $branch->id,
            'resource_type' => 'cage',
            'code' => $code,
            'name' => 'Jaula ' . $code,
            'capacity_label' => 'Mediana',
            'administrative_status' => 'active',
            'operational_status' => 'available',
            'notes' => 'Activo de prueba para trazabilidad fotográfica.',
        ]);
    }
}