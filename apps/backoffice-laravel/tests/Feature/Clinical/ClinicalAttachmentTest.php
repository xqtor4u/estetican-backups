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
        Storage::disk('public')->assertExists($attachment->file_path);

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
        Storage::disk('public')->assertExists($attachment->file_path);
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
}
