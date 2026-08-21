<?php

namespace App\Rules;

use App\Support\SystemSettings\SystemSettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Acepta el número con cualquier formato de captura (espacios, guiones, paréntesis) pero exige
 * que, sin esos separadores, el total de dígitos caiga dentro de [minDigits, maxDigits].
 *
 * Por default exige exactamente 10 (estándar México/Norteamérica, sin código de país). Si el
 * tenant activó "commercial_clients_phone_allow_country_code" en Configuración → Clientes (para
 * clínicas con clientes de zona fronteriza o de otro país), se amplía a [8, 15] — 15 es el
 * máximo real del estándar internacional E.164 de la UIT (código de país de 1-3 dígitos + número
 * nacional, sin contar el signo "+" ni prefijos de salida); 8 cubre incluso números nacionales
 * cortos (7 dígitos) con el código de país más corto (1 dígito).
 */
class ValidPhoneNumber implements ValidationRule
{
    public function __construct(
        public readonly int $minDigits = 10,
        public readonly int $maxDigits = 10,
    ) {}

    public static function fromSettings(SystemSettings $settings): self
    {
        $allowsCountryCode = (bool) ($settings->all()['commercial_clients_phone_allow_country_code'] ?? false);

        return $allowsCountryCode ? new self(minDigits: 8, maxDigits: 15) : new self();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $length = strlen(preg_replace('/\D+/', '', (string) $value));

        if ($length >= $this->minDigits && $length <= $this->maxDigits) {
            return;
        }

        $fail($this->minDigits === $this->maxDigits
            ? "El teléfono debe tener {$this->minDigits} dígitos."
            : "El teléfono debe tener entre {$this->minDigits} y {$this->maxDigits} dígitos.");
    }
}
