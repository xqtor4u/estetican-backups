<?php

namespace App\Domain\Clinical\Exceptions;

use RuntimeException;

class ClinicalVisitLockedException extends RuntimeException
{
    public static function forVisit(int $visitId): self
    {
        return new self("La visita clínica #{$visitId} ya está firmada y es inmutable. Para corregirla, crea una nota aclaratoria (enmienda) nueva.");
    }
}
