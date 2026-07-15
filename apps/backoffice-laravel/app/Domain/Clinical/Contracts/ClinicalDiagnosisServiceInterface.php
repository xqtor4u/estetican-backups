<?php

namespace App\Domain\Clinical\Contracts;

use App\Models\ClinicalDiagnosis;
use App\Models\PetCondition;

interface ClinicalDiagnosisServiceInterface
{
    /**
     * Promueve un diagnóstico puntual a una condición crónica/problem list activa,
     * enlazando ambos registros en las dos direcciones para navegación desde la UI.
     */
    public function promoteToCondition(ClinicalDiagnosis $diagnosis, array $overrides = []): PetCondition;
}
