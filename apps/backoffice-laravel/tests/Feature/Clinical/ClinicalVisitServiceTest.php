<?php

namespace Tests\Feature\Clinical;

use App\Domain\Clinical\Contracts\ClinicalDiagnosisServiceInterface;
use App\Domain\Clinical\Contracts\ClinicalVisitServiceInterface;
use App\Domain\Clinical\Exceptions\ClinicalVisitLockedException;
use App\Domain\Clinical\Exceptions\UnauthorizedClinicalSignatureException;
use App\Models\Client;
use App\Models\ClinicalDiagnosis;
use App\Models\Operator;
use App\Models\OperatorRole;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClinicalVisitServiceTest extends TestCase
{
    use RefreshDatabase;

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function veterinarian(bool $withLicense = true): array
    {
        $role = OperatorRole::create(['code' => 'veterinario', 'acronym' => 'VET', 'name' => 'Veterinario', 'can_login' => true]);

        $operator = Operator::create([
            'code' => 'VET'.uniqid(),
            'name' => 'Dra. Ana Vet',
            'full_name' => 'Dra. Ana Vet',
            'operator_role_id' => $role->id,
            'professional_license' => $withLicense ? 'CED-123456' : null,
            'is_active' => true,
        ]);

        Permission::firstOrCreate(['name' => 'clinico.firmar', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'vet'.uniqid(),
            'first_name' => 'Ana',
            'last_name' => 'Vet',
            'email' => 'vet'.uniqid().'@example.com',
            'password' => bcrypt('secret123'),
            'operator_id' => $operator->id,
            'is_active' => true,
        ]);
        $user->givePermissionTo('clinico.firmar');

        return [$operator, $user];
    }

    public function test_creates_a_draft_visit_and_mirrors_weight_into_pet_weights(): void
    {
        $pet = $this->pet();
        [$operator] = $this->veterinarian();

        $visit = app(ClinicalVisitServiceInterface::class)->createDraft([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visit_type' => 'consultation',
            'visited_at' => now(),
            'reason_for_visit' => 'Chequeo general',
            'weight_kg' => 12.4,
        ]);

        $this->assertSame('draft', $visit->status);
        $this->assertDatabaseHas('pet_weights', [
            'pet_id' => $pet->id,
            'clinical_visit_id' => $visit->id,
            'weight_kg' => 12.4,
            'source' => 'clinical_visit',
        ]);
    }

    public function test_signing_requires_permission_operator_role_and_license(): void
    {
        $pet = $this->pet();
        [$operator, $user] = $this->veterinarian(withLicense: false);

        $visit = app(ClinicalVisitServiceInterface::class)->createDraft([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visit_type' => 'consultation',
            'visited_at' => now(),
            'reason_for_visit' => 'Chequeo general',
        ]);

        $this->expectException(UnauthorizedClinicalSignatureException::class);
        $this->expectExceptionMessage('cédula profesional');

        app(ClinicalVisitServiceInterface::class)->sign($visit, $user);
    }

    public function test_signing_succeeds_and_locks_the_visit_from_further_edits(): void
    {
        $pet = $this->pet();
        [$operator, $user] = $this->veterinarian();

        $visit = app(ClinicalVisitServiceInterface::class)->createDraft([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visit_type' => 'consultation',
            'visited_at' => now(),
            'reason_for_visit' => 'Chequeo general',
        ]);

        $signed = app(ClinicalVisitServiceInterface::class)->sign($visit, $user);

        $this->assertSame('signed', $signed->status);
        $this->assertSame('CED-123456', $signed->professional_license_snapshot);
        $this->assertNotNull($signed->signed_at);

        $this->expectException(ClinicalVisitLockedException::class);
        $signed->update(['assessment' => 'intento de edición post-firma']);
    }

    public function test_amendment_keeps_the_original_intact_and_linked(): void
    {
        $pet = $this->pet();
        [$operator, $user] = $this->veterinarian();

        $visit = app(ClinicalVisitServiceInterface::class)->createDraft([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visit_type' => 'consultation',
            'visited_at' => now(),
            'reason_for_visit' => 'Chequeo general',
            'assessment' => 'Sano',
        ]);
        $signed = app(ClinicalVisitServiceInterface::class)->sign($visit, $user);

        $amendment = app(ClinicalVisitServiceInterface::class)->createAmendment($signed, [
            'operator_id' => $operator->id,
            'visit_type' => 'follow_up',
            'visited_at' => now(),
            'reason_for_visit' => 'Corrección',
            'assessment' => 'Se detectó otoscopía anormal, se omitió en la nota original',
            'amendment_reason' => 'Se me olvidó anotar el hallazgo en el oído',
        ]);

        $signed->refresh();

        $this->assertSame('amended', $signed->status);
        $this->assertSame('Sano', $signed->assessment, 'La visita original no debe perder su contenido clínico');
        $this->assertSame($signed->id, $amendment->amends_visit_id);
        $this->assertSame('draft', $amendment->status);

        // La original sigue bloqueada tras la enmienda (no se re-habilita accidentalmente)
        $this->expectException(ClinicalVisitLockedException::class);
        $signed->update(['assessment' => 'otro intento']);
    }

    public function test_promoting_a_diagnosis_creates_a_linked_chronic_condition(): void
    {
        $pet = $this->pet();
        [$operator] = $this->veterinarian();

        $visit = app(ClinicalVisitServiceInterface::class)->createDraft([
            'pet_id' => $pet->id,
            'operator_id' => $operator->id,
            'visit_type' => 'consultation',
            'visited_at' => now(),
            'reason_for_visit' => 'Chequeo general',
        ]);

        $diagnosis = ClinicalDiagnosis::create([
            'clinical_visit_id' => $visit->id,
            'pet_id' => $pet->id,
            'diagnosis' => 'Dermatitis atópica',
            'diagnosis_type' => 'definitive',
        ]);

        $condition = app(ClinicalDiagnosisServiceInterface::class)->promoteToCondition($diagnosis);

        $this->assertSame('Dermatitis atópica', $condition->name);
        $this->assertSame($diagnosis->id, $condition->promoted_from_diagnosis_id);
        $this->assertSame($condition->id, $diagnosis->fresh()->promoted_to_condition_id);
    }
}
