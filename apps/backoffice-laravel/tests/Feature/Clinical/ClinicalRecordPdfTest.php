<?php

namespace Tests\Feature\Clinical;

use App\Models\Client;
use App\Models\ClinicalDiagnosis;
use App\Models\ClinicalPrescription;
use App\Models\ClinicalPrescriptionItem;
use App\Models\ClinicalVisit;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\PetAllergy;
use App\Models\PetVaccination;
use App\Models\PetWeight;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClinicalRecordPdfTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): Operator
    {
        return Operator::create(['code' => 'VET'.uniqid(), 'name' => 'Dra. Vet', 'first_name' => 'Dra. Vet', 'is_active' => true, 'professional_license' => 'CED-12345']);
    }

    private function petWithFullHistory(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
        $operator = $this->operator();

        $visit = ClinicalVisit::create([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visited_at' => now(),
            'reason_for_visit' => 'Chequeo general',
            'subjective' => 'Come bien, activo',
            'weight_kg' => 12.5,
            'assessment' => 'Sano',
            'plan' => 'Control en 6 meses',
        ]);

        ClinicalDiagnosis::create([
            'clinical_visit_id' => $visit->id,
            'pet_id' => $pet->id,
            'diagnosis' => 'Otitis leve',
            'diagnosis_type' => 'presumptive',
        ]);

        $prescription = ClinicalPrescription::create([
            'clinical_visit_id' => $visit->id,
            'pet_id' => $pet->id,
            'prescribed_by_operator_id' => $operator->id,
            'prescribed_at' => now(),
            'general_instructions' => 'Administrar con alimento',
        ]);

        ClinicalPrescriptionItem::create([
            'clinical_prescription_id' => $prescription->id,
            'drug_name' => 'Amoxicilina',
            'concentration' => '250mg',
            'dose' => '1 tableta',
            'route' => 'oral',
            'frequency' => 'cada 12 horas',
            'duration_days' => 7,
        ]);

        PetAllergy::create([
            'pet_id' => $pet->id,
            'allergen' => 'Pollo',
            'allergen_type' => 'food',
            'severity' => 'moderate',
            'is_active' => true,
        ]);

        PetWeight::create([
            'pet_id' => $pet->id,
            'weight_kg' => 12.5,
            'measured_at' => now(),
        ]);

        PetVaccination::create([
            'pet_id' => $pet->id,
            'vaccine_name' => 'Rabia',
            'applied_at' => now()->subMonths(2),
            'expires_at' => now()->addMonths(10),
        ]);

        return $pet;
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
    }

    public function test_pet_record_pdf_renders_full_history(): void
    {
        $pet = $this->petWithFullHistory();
        $user = $this->userWithClinicalPermissions(['ver clinico']);

        $response = $this->actingAs($user)->get(route('clinical.pets.record.pdf', $pet));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pet_record_pdf_requires_ver_clinico_permission(): void
    {
        $pet = $this->petWithFullHistory();
        $user = $this->userWithClinicalPermissions([]);

        $response = $this->actingAs($user)->get(route('clinical.pets.record.pdf', $pet));

        $response->assertForbidden();
    }

    public function test_pet_record_pdf_respects_module_gate(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => false]);
        $pet = $this->petWithFullHistory();
        $user = $this->userWithClinicalPermissions(['ver clinico']);

        $response = $this->actingAs($user)->get(route('clinical.pets.record.pdf', $pet));

        $response->assertNotFound();
    }

    public function test_prescription_pdf_renders(): void
    {
        $pet = $this->petWithFullHistory();
        $prescription = ClinicalPrescription::first();
        $user = $this->userWithClinicalPermissions(['ver clinico']);

        $response = $this->actingAs($user)->get(route('clinical.prescriptions.pdf', $prescription));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_prescription_pdf_requires_ver_clinico_permission(): void
    {
        $pet = $this->petWithFullHistory();
        $prescription = ClinicalPrescription::first();
        $user = $this->userWithClinicalPermissions([]);

        $response = $this->actingAs($user)->get(route('clinical.prescriptions.pdf', $prescription));

        $response->assertForbidden();
    }
}
