<?php

namespace Tests\Feature\Clinical;

use App\Domain\Clinical\Services\VaccinationEligibilityChecker;
use App\Models\Client;
use App\Models\Pet;
use App\Models\PetVaccination;
use App\Models\Service;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaccinationEligibilityCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function pet(): Pet
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);

        return Pet::create(['client_id' => $client->id, 'name' => 'Luka']);
    }

    private function enableModule(): void
    {
        app(SystemSettings::class)->saveFields('clinical', ['clinical_module_enabled' => true]);
    }

    private function coreVaccineService(string $name): Service
    {
        return Service::create([
            'code' => 'VAC-'.uniqid(),
            'type' => 'vaccine',
            'name' => $name,
            'price' => 0,
            'duration_minutes' => 10,
            'is_active' => true,
            'is_core_vaccine' => true,
        ]);
    }

    public function test_returns_null_when_no_core_vaccine_services_exist(): void
    {
        $pet = $this->pet();
        $this->enableModule();

        $result = app(VaccinationEligibilityChecker::class)->check($pet);

        $this->assertNull($result);
    }

    public function test_returns_null_when_module_is_disabled_even_with_core_vaccines_configured(): void
    {
        $pet = $this->pet();
        $this->coreVaccineService('Rabia');
        // clinical_module_enabled queda en su default (false) — no se llama enableModule()

        $result = app(VaccinationEligibilityChecker::class)->check($pet);

        $this->assertNull($result);
    }

    public function test_flags_missing_vaccines_when_none_are_registered(): void
    {
        $pet = $this->pet();
        $this->enableModule();
        $this->coreVaccineService('Rabia');
        $this->coreVaccineService('Múltiple');

        $result = app(VaccinationEligibilityChecker::class)->check($pet);

        $this->assertNotNull($result);
        $this->assertEqualsCanonicalizing(['Rabia', 'Múltiple'], $result['missing_vaccines']);
    }

    public function test_flags_expired_vaccine_as_missing(): void
    {
        $pet = $this->pet();
        $this->enableModule();
        $rabia = $this->coreVaccineService('Rabia');
        PetVaccination::create([
            'pet_id' => $pet->id,
            'service_id' => $rabia->id,
            'vaccine_name' => $rabia->name,
            'applied_at' => now()->subYears(2),
            'expires_at' => now()->subDay(),
        ]);

        $result = app(VaccinationEligibilityChecker::class)->check($pet);

        $this->assertNotNull($result);
        $this->assertSame(['Rabia'], $result['missing_vaccines']);
    }

    public function test_returns_null_when_all_core_vaccines_are_valid(): void
    {
        $pet = $this->pet();
        $this->enableModule();
        $rabia = $this->coreVaccineService('Rabia');
        $multiple = $this->coreVaccineService('Múltiple');
        PetVaccination::create(['pet_id' => $pet->id, 'service_id' => $rabia->id, 'vaccine_name' => $rabia->name, 'expires_at' => now()->addMonths(6)]);
        PetVaccination::create(['pet_id' => $pet->id, 'service_id' => $multiple->id, 'vaccine_name' => $multiple->name, 'expires_at' => now()->addMonths(6)]);

        $result = app(VaccinationEligibilityChecker::class)->check($pet);

        $this->assertNull($result);
    }

    public function test_a_valid_externally_applied_vaccine_counts_as_protection(): void
    {
        // El objetivo del checker es "¿está protegida la mascota?", no "¿se cobró en EstetiCAN?" —
        // una vacuna aplicada externamente (otro veterinario, campaña) igual debe contar.
        $pet = $this->pet();
        $this->enableModule();
        $rabia = $this->coreVaccineService('Rabia');
        PetVaccination::create([
            'pet_id' => $pet->id,
            'service_id' => $rabia->id,
            'vaccine_name' => $rabia->name,
            'is_external' => true,
            'expires_at' => now()->addMonths(6),
        ]);

        $result = app(VaccinationEligibilityChecker::class)->check($pet);

        $this->assertNull($result);
    }

    public function test_ignores_non_core_vaccine_services(): void
    {
        $pet = $this->pet();
        $this->enableModule();
        Service::create([
            'code' => 'VAC-'.uniqid(),
            'type' => 'vaccine',
            'name' => 'Tos de las perreras',
            'price' => 0,
            'duration_minutes' => 10,
            'is_active' => true,
            'is_core_vaccine' => false,
        ]);

        $result = app(VaccinationEligibilityChecker::class)->check($pet);

        $this->assertNull($result);
    }
}
