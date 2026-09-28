<?php

namespace App\Support\SystemSettings;

use App\Models\Branch;
use Carbon\Carbon;

/**
 * Horario operativo del negocio. B2 (27/09/2026): cada sucursal puede tener el suyo
 * (`branches.opening_time`/`closing_time`); sin él se usa el general de Configuración.
 * `for($branchId)` devuelve el horario de esa sucursal; sin `for()`, el general.
 */
class BusinessHours
{
    private ?int $branchId = null;

    /** @var array{0: ?string, 1: ?string}|null */
    private ?array $branchHours = null;

    public function __construct(private SystemSettings $settings) {}

    public function for(?int $branchId): self
    {
        $scoped = clone $this;
        $scoped->branchId = $branchId;
        $scoped->branchHours = null;

        return $scoped;
    }

    public function openingTime(): string
    {
        return $this->ownHours()[0] ?? (string) ($this->settings->all()['booking_opening_time'] ?? '09:00');
    }

    public function closingTime(): string
    {
        return $this->ownHours()[1] ?? (string) ($this->settings->all()['booking_closing_time'] ?? '19:00');
    }

    public function isWithin(Carbon $dateTime): bool
    {
        $time = $dateTime->format('H:i');

        return $time >= $this->openingTime() && $time <= $this->closingTime();
    }

    /** @return array{0: ?string, 1: ?string} Horario propio de la sucursal, solo si tiene los dos. */
    private function ownHours(): array
    {
        if (! $this->branchId) {
            return [null, null];
        }

        if ($this->branchHours === null) {
            $branch = Branch::find($this->branchId, ['opening_time', 'closing_time']);
            $this->branchHours = $branch && $branch->opening_time && $branch->closing_time
                ? [$branch->opening_time, $branch->closing_time]
                : [null, null];
        }

        return $this->branchHours;
    }
}
