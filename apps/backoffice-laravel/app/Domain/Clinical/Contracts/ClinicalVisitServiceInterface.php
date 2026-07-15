<?php

namespace App\Domain\Clinical\Contracts;

use App\Models\ClinicalVisit;
use App\Models\User;

interface ClinicalVisitServiceInterface
{
    /**
     * Crea una visita clínica en estado borrador. Si trae `weight_kg`, espeja el peso
     * en `pet_weights` (source = 'clinical_visit') para alimentar la tendencia histórica.
     */
    public function createDraft(array $data): ClinicalVisit;

    /**
     * Actualiza una visita en estado borrador. Lanza ClinicalVisitLockedException si ya
     * está firmada (el guard vive también en el modelo, esto da un mensaje más claro antes).
     */
    public function updateDraft(ClinicalVisit $visit, array $data): ClinicalVisit;

    /**
     * Firma la visita, dejándola inmutable. Exige permiso `clinico.firmar`, operator_role
     * 'veterinario', y cédula profesional cargada — lanza excepción de dominio si falta algo.
     * Congela `operators.professional_license` en `professional_license_snapshot`.
     */
    public function sign(ClinicalVisit $visit, User $signer): ClinicalVisit;

    /**
     * Crea una nota aclaratoria (enmienda) ligada a una visita ya firmada. Nunca edita la
     * original — la marca como 'amended' (solo el estado) y crea un registro nuevo enlazado.
     */
    public function createAmendment(ClinicalVisit $original, array $data): ClinicalVisit;
}
