<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\ResourceEvent;
use App\Models\ResourceEventPhoto;
use App\Models\ResourceEventUpdate;
use App\Models\Service;
use App\Models\User;
use App\Support\ResourceEventPhotoImageManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ResourceEventCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_can_create_event_register_follow_up_and_manage_stage_photos(): void
    {
        Storage::fake('public');

        $resource = $this->makeResource('J-E01');
        $detector = User::create([
            'name' => 'Detector Uno',
            'email' => 'detector@example.com',
            'password' => 'secret123',
        ]);
        $responsible = User::create([
            'name' => 'Responsable Uno',
            'email' => 'responsable@example.com',
            'password' => 'secret123',
        ]);
        $client = Client::create([
            'first_name' => 'Ana',
            'apellido_paterno' => 'Lopez',
            'email' => 'ana-resource@example.com',
        ]);
        $pet = Pet::create([
            'client_id' => $client->id,
            'name' => 'Nina',
            'species' => 'perro',
        ]);
        $service = Service::create([
            'code' => 'SPA-EVT',
            'type' => 'spa',
            'name' => 'Bano express',
            'description' => 'Servicio vinculado a incidente',
            'price' => '250.00',
            'suggested_price' => '250.00',
            'duration_minutes' => 45,
            'suggested_duration_minutes' => 45,
            'is_active' => true,
        ]);

        $showResponse = $this->get(route('resources.show', $resource));
        $showResponse->assertOk();
        $showResponse->assertSee('Eventos operativos');
        $showResponse->assertSee('Nuevo evento');

        $eventCreateResponse = $this->post(route('resources.events.store', $resource), [
            'event_type' => 'incidente',
            'event_status' => 'open',
            'severity' => 'high',
            'title' => 'Golpe lateral en puerta',
            'description' => 'Se detecta golpe posterior al servicio.',
            'occurred_at' => '2026-03-26 18:00:00',
            'detected_at' => '2026-03-26 18:15:00',
            'detected_by_user_id' => $detector->id,
            'responsible_user_id' => $responsible->id,
            'client_id' => $client->id,
            'pet_id' => $pet->id,
            'service_id' => $service->id,
        ]);

        $event = ResourceEvent::firstOrFail();

        $eventCreateResponse->assertRedirect(route('resources.events.show', [$resource, $event]));
        $this->assertDatabaseHas('resource_events', [
            'id' => $event->id,
            'resource_id' => $resource->id,
            'event_type' => 'incidente',
            'event_status' => 'open',
            'severity' => 'high',
            'client_id' => $client->id,
            'pet_id' => $pet->id,
            'service_id' => $service->id,
            'detected_by_user_id' => $detector->id,
            'responsible_user_id' => $responsible->id,
        ]);

        $eventShowResponse = $this->get(route('resources.events.show', [$resource, $event]));
        $eventShowResponse->assertOk();
        $eventShowResponse->assertSee('Golpe lateral en puerta');
        $eventShowResponse->assertSee('Detector Uno');
        $eventShowResponse->assertSee('Responsable Uno');
        $eventShowResponse->assertSee('Nina');
        $eventShowResponse->assertSee('Bano express');

        $this->actingAs($responsible);

        $updateResponse = $this->post(route('resources.events.updates.store', [$resource, $event]), [
            'update_type' => 'diagnostico',
            'to_status' => 'in_progress',
            'notes' => 'Se aísla la jaula y se programa reparación.',
        ]);

        $update = ResourceEventUpdate::firstOrFail();

        $updateResponse->assertRedirect(route('resources.events.show', [$resource, $event]) . '#event-updates');
        $this->assertDatabaseHas('resource_event_updates', [
            'id' => $update->id,
            'resource_event_id' => $event->id,
            'update_type' => 'diagnostico',
            'from_status' => 'open',
            'to_status' => 'in_progress',
            'created_by_user_id' => $responsible->id,
        ]);
        $this->assertDatabaseHas('resource_events', [
            'id' => $event->id,
            'event_status' => 'in_progress',
        ]);

        $photoCreateResponse = $this->post(route('resources.events.photos.store', [$resource, $event]), [
            'photo' => UploadedFile::fake()->image('evento-1.jpg', 4200, 2800),
            'resource_event_update_id' => $update->id,
            'photo_type' => 'evidencia',
            'taken_at' => '2026-03-26 18:20:00',
            'description' => 'Golpe visible en puerta lateral',
            'is_primary' => '1',
        ]);

        $firstPhoto = ResourceEventPhoto::firstOrFail();

        $photoCreateResponse->assertRedirect(route('resources.events.show', [$resource, $event]) . '#event-photos');
        Storage::disk('public')->assertExists($firstPhoto->photo_url);
        Storage::disk('public')->assertExists($firstPhoto->thumbnail_storage_path);

        [$firstWidth, $firstHeight] = getimagesize(Storage::disk('public')->path($firstPhoto->photo_url));
        [$firstThumbWidth, $firstThumbHeight] = getimagesize(Storage::disk('public')->path($firstPhoto->thumbnail_storage_path));

        $this->assertLessThanOrEqual(1600, max($firstWidth, $firstHeight));
        $this->assertSame(160, $firstThumbWidth);
        $this->assertSame(120, $firstThumbHeight);

        $this->post(route('resources.events.photos.store', [$resource, $event]), [
            'photo' => UploadedFile::fake()->image('evento-2.jpg', 3200, 2200),
            'resource_event_update_id' => $update->id,
            'photo_type' => 'seguimiento',
            'taken_at' => '2026-03-26 18:40:00',
            'description' => 'Evidencia tras ajuste inicial',
            'is_primary' => '1',
        ])->assertRedirect(route('resources.events.show', [$resource, $event]) . '#event-photos');

        $this->assertDatabaseHas('resource_event_photos', [
            'id' => $firstPhoto->id,
            'is_primary' => false,
        ]);

        $secondPhoto = ResourceEventPhoto::orderByDesc('id')->firstOrFail();
        $secondPhotoOriginalPath = $secondPhoto->photo_url;
        $secondPhotoOriginalThumbPath = $secondPhoto->thumbnail_storage_path;

        $this->put(route('resources.events.photos.update', [$resource, $event, $secondPhoto]), [
            'photo' => UploadedFile::fake()->image('evento-2-reemplazo.jpg', 3000, 2100),
            'resource_event_update_id' => $update->id,
            'photo_type' => 'cierre',
            'taken_at' => '2026-03-26 19:00:00',
            'description' => 'Foto corregida tras reparación',
            'is_primary' => '0',
        ])->assertRedirect(route('resources.events.show', [$resource, $event]) . '#event-photos');

        $secondPhoto->refresh();

        $this->assertDatabaseHas('resource_event_photos', [
            'id' => $secondPhoto->id,
            'photo_type' => 'cierre',
            'resource_event_update_id' => $update->id,
            'is_primary' => false,
        ]);
        Storage::disk('public')->assertMissing($secondPhotoOriginalPath);
        Storage::disk('public')->assertMissing($secondPhotoOriginalThumbPath);
        Storage::disk('public')->assertExists($secondPhoto->photo_url);
        Storage::disk('public')->assertExists($secondPhoto->thumbnail_storage_path);

        $this->delete(route('resources.events.photos.destroy', [$resource, $event, $secondPhoto]))
            ->assertRedirect(route('resources.events.show', [$resource, $event]) . '#event-photos');

        $this->assertDatabaseMissing('resource_event_photos', ['id' => $secondPhoto->id]);
        Storage::disk('public')->assertMissing($secondPhoto->photo_url);
        Storage::disk('public')->assertMissing($secondPhoto->thumbnail_storage_path);
    }

    public function test_event_photo_store_prefills_taken_at_from_metadata_when_missing(): void
    {
        $resource = $this->makeResource('J-E02');
        $event = ResourceEvent::create([
            'resource_id' => $resource->id,
            'event_type' => 'incidente',
            'event_status' => 'open',
            'severity' => 'medium',
            'title' => 'Evento con EXIF',
            'detected_at' => '2026-03-26 19:00:00',
        ]);
        $file = UploadedFile::fake()->image('evento-exif.jpg', 2200, 1600);

        $manager = Mockery::mock(ResourceEventPhotoImageManager::class);
        $manager->shouldReceive('extractTakenAt')
            ->once()
            ->andReturn(CarbonImmutable::parse('2024-04-05 14:15:16'));
        $manager->shouldReceive('store')
            ->once()
            ->andReturn('resource-event-photos/2026/03/original/evento-exif.jpg');
        $manager->shouldReceive('deleteFiles')
            ->never();

        $this->app->instance(ResourceEventPhotoImageManager::class, $manager);

        $this->post(route('resources.events.photos.store', [$resource, $event]), [
            'photo' => $file,
            'photo_type' => 'evidencia',
            'taken_at' => '',
            'description' => 'Foto con EXIF',
            'is_primary' => '1',
        ])->assertRedirect(route('resources.events.show', [$resource, $event]) . '#event-photos');

        $storedPhoto = ResourceEventPhoto::firstOrFail();

        $this->assertSame('2024-04-05 14:15:16', $storedPhoto->taken_at?->format('Y-m-d H:i:s'));
        $this->assertSame('resource-event-photos/2026/03/original/evento-exif.jpg', $storedPhoto->photo_url);
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
            'notes' => 'Activo de prueba para eventos operativos.',
        ]);
    }
}