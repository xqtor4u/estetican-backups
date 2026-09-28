<?php

namespace Tests\Feature\Clinical;

use App\Models\Client;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalVisit;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClinicalAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function userWithClinicalPermissions(array $permissions): User
    {
        $user = User::create([
            'name' => 'staff'.uniqid(),
            'first_name' => 'Staff',
            'apellido_paterno' => 'Test',
            'email' => 'staff'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_uploads_an_image_attachment_and_optimizes_it_without_cropping(): void
    {
        $pet = $this->pet();
        $user = $this->userWithClinicalPermissions(['ver clinico', 'crear clinico']);

        $response = $this->actingAs($user)->post(route('clinical.attachments.store', $pet), [
            'attachment_type' => 'xray',
            'file' => UploadedFile::fake()->image('radiografia.jpg', 3000, 2000),
            'description' => 'Radiografía de cadera',
            'performed_at' => '2026-07-01',
            'performed_by' => 'Laboratorio Central',
        ]);

        $response->assertRedirect(route('clinical.pets.show', $pet).'#attachments');

        $attachment = ClinicalAttachment::first();
        $this->assertNotNull($attachment);
        $this->assertSame('xray', $attachment->attachment_type);
        $this->assertSame('image/jpeg', $attachment->file_mime_type);
        $this->assertStringEndsWith('.jpg', $attachment->file_path);
        Storage::disk('local')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);

        $folderPage = $this->actingAs($user)->get(route('clinical.pets.show', $pet));
        $folderPage->assertOk();
        $folderPage->assertSee('Radiografía de cadera');
        $folderPage->assertSee('Laboratorio Central');
    }

    public function test_uploads_a_pdf_attachment_without_touching_its_bytes(): void
    {
        $pet = $this->pet();
        $user = $this->userWithClinicalPermissions(['ver clinico', 'crear clinico']);

        $response = $this->actingAs($user)->post(route('clinical.attachments.store', $pet), [
            'attachment_type' => 'lab_result',
            'file' => UploadedFile::fake()->create('resultado.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect(route('clinical.pets.show', $pet).'#attachments');

        $attachment = ClinicalAttachment::first();
        $this->assertSame('application/pdf', $attachment->file_mime_type);
        $this->assertStringEndsWith('.pdf', $attachment->file_path);
        Storage::disk('local')->assertExists($attachment->file_path);
        Storage::disk('public')->assertMissing($attachment->file_path);
    }

    public function test_store_requires_crear_clinico_permission(): void
    {
        $pet = $this->pet();
        $user = $this->userWithClinicalPermissions(['ver clinico']);

        $response = $this->actingAs($user)->post(route('clinical.attachments.store', $pet), [
            'attachment_type' => 'other',
            'file' => UploadedFile::fake()->create('archivo.pdf', 100, 'application/pdf'),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, ClinicalAttachment::count());
    }

    public function test_clinical_visit_id_must_belong_to_the_same_pet(): void
    {
        $pet = $this->pet();
        $otherPet = $this->pet();
        $operator = Operator::create(['code' => 'VET'.uniqid(), 'name' => 'Dra. Vet', 'first_name' => 'Dra. Vet', 'is_active' => true]);
        $foreignVisit = ClinicalVisit::create([
            'pet_id' => $otherPet->id,
            'operator_id' => $operator->id,
            'visited_at' => now(),
            'reason_for_visit' => 'Consulta',
        ]);

        $user = $this->userWithClinicalPermissions(['ver clinico', 'crear clinico']);

        $response = $this->actingAs($user)->post(route('clinical.attachments.store', $pet), [
            'attachment_type' => 'other',
            'file' => UploadedFile::fake()->create('archivo.pdf', 100, 'application/pdf'),
            'clinical_visit_id' => $foreignVisit->id,
        ]);

        $response->assertSessionHasErrors('clinical_visit_id');
        $this->assertSame(0, ClinicalAttachment::count());
    }

    public function test_destroy_deletes_the_stored_file_and_the_record(): void
    {
        $pet = $this->pet();
        $user = $this->userWithClinicalPermissions(['ver clinico', 'crear clinico', 'editar clinico']);

        $this->actingAs($user)->post(route('clinical.attachments.store', $pet), [
            'attachment_type' => 'other',
            'file' => UploadedFile::fake()->create('archivo.pdf', 100, 'application/pdf'),
        ]);
        $attachment = ClinicalAttachment::first();
        $filePath = $attachment->file_path;

        $response = $this->actingAs($user)->delete(route('clinical.attachments.destroy', [$pet, $attachment]));

        $response->assertRedirect(route('clinical.pets.show', $pet).'#attachments');
        $this->assertSame(0, ClinicalAttachment::count());
        Storage::disk('public')->assertMissing($filePath);
        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_destroy_rejects_an_attachment_belonging_to_a_different_pet(): void
    {
        $pet = $this->pet();
        $otherPet = $this->pet();
        $user = $this->userWithClinicalPermissions(['ver clinico', 'crear clinico', 'editar clinico']);

        $this->actingAs($user)->post(route('clinical.attachments.store', $otherPet), [
            'attachment_type' => 'other',
            'file' => UploadedFile::fake()->create('archivo.pdf', 100, 'application/pdf'),
        ]);
        $attachment = ClinicalAttachment::first();

        $response = $this->actingAs($user)->delete(route('clinical.attachments.destroy', [$pet, $attachment]));

        $response->assertNotFound();
        $this->assertSame(1, ClinicalAttachment::count());
    }

    /** Sube un PDF como usuario con permisos completos y devuelve [mascota, adjunto]. */
    private function uploadedPdf(): array
    {
        $pet = $this->pet();
        $this->actingAs($this->userWithClinicalPermissions(['ver clinico', 'crear clinico']))
            ->post(route('clinical.attachments.store', $pet), [
                'attachment_type' => 'lab_result',
                'file' => UploadedFile::fake()->createWithContent('resultado.pdf', '%PDF-1.4 laboratorio'),
            ]);

        return [$pet, ClinicalAttachment::firstOrFail()];
    }

    private function signedUrl(Pet $pet, ClinicalAttachment $attachment, int $minutes = 30): string
    {
        return URL::temporarySignedRoute('clinical.attachments.show', now()->addMinutes($minutes), [$pet, $attachment]);
    }

    public function test_attachment_opens_with_session_permission_and_a_valid_signed_link(): void
    {
        [$pet, $attachment] = $this->uploadedPdf();
        $viewer = $this->userWithClinicalPermissions(['ver clinico']);

        $response = $this->actingAs($viewer)->get($this->signedUrl($pet, $attachment));

        $response->assertOk();
        $this->assertSame('%PDF-1.4 laboratorio', $response->streamedContent());
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_attachment_is_refused_without_signature_expired_link_permission_or_session(): void
    {
        [$pet, $attachment] = $this->uploadedPdf();
        $viewer = $this->userWithClinicalPermissions(['ver clinico']);
        $noClinical = $this->userWithClinicalPermissions([]);

        $this->actingAs($viewer)->get(route('clinical.attachments.show', [$pet, $attachment]))->assertForbidden();

        $expired = $this->signedUrl($pet, $attachment, 30);
        $this->travel(31)->minutes();
        $this->actingAs($viewer)->get($expired)->assertForbidden();
        $this->travelBack();

        $this->actingAs($noClinical)->get($this->signedUrl($pet, $attachment))->assertForbidden();

        auth()->logout();
        $this->get($this->signedUrl($pet, $attachment))->assertRedirect(route('login'));
    }

    public function test_attachment_of_another_pet_is_not_found(): void
    {
        [, $attachment] = $this->uploadedPdf();
        $otherPet = $this->pet();

        $this->actingAs($this->userWithClinicalPermissions(['ver clinico']))
            ->get($this->signedUrl($otherPet, $attachment))
            ->assertNotFound();
    }

    public function test_clinical_folder_links_to_the_protected_route_not_to_public_storage(): void
    {
        [$pet, $attachment] = $this->uploadedPdf();

        $html = $this->actingAs($this->userWithClinicalPermissions(['ver clinico']))
            ->get(route('clinical.pets.show', $pet))->assertOk()->getContent();

        $this->assertStringContainsString('/clinico/mascotas/'.$pet->id.'/adjuntos/'.$attachment->id.'?expires=', $html);
        $this->assertStringNotContainsString('/storage/'.$attachment->file_path, $html);
    }

    public function test_command_moves_legacy_public_attachments_to_the_private_disk(): void
    {
        $pet = $this->pet();
        Storage::disk('public')->put('clinical-attachments/2026/08/viejo.pdf', '%PDF viejo');
        $attachment = $pet->attachments()->create([
            'attachment_type' => 'lab_result',
            'file_path' => 'clinical-attachments/2026/08/viejo.pdf',
            'file_mime_type' => 'application/pdf',
        ]);

        $this->artisan('clinico:mover-adjuntos-privados')->assertSuccessful();
        $this->artisan('clinico:mover-adjuntos-privados')->assertSuccessful();

        Storage::disk('public')->assertMissing($attachment->file_path);
        $this->assertSame('%PDF viejo', Storage::disk('local')->get($attachment->file_path));
    }
}
