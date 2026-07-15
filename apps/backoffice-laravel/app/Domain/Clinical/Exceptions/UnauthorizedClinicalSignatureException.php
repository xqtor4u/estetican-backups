<?php

namespace App\Domain\Clinical\Exceptions;

use RuntimeException;

class UnauthorizedClinicalSignatureException extends RuntimeException
{
    public static function missingPermission(): self
    {
        return new self('No tienes el permiso "clinico.firmar" para firmar visitas clínicas.');
    }

    public static function notVeterinarian(): self
    {
        return new self('Solo un operador con rol "Veterinario" puede firmar una visita clínica.');
    }

    public static function missingProfessionalLicense(): self
    {
        return new self('El operador no tiene cédula profesional cargada — complétala en su perfil antes de firmar.');
    }

    public static function notInDraft(): self
    {
        return new self('Solo se puede firmar una visita en estado borrador.');
    }

    public static function isExternal(): self
    {
        return new self('Las visitas externas no se firman — son una transcripción de un acto médico ajeno a EstetiCAN.');
    }

    public static function originalNotSigned(): self
    {
        return new self('Solo se puede crear una nota aclaratoria sobre una visita ya firmada.');
    }
}
