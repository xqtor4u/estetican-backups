<?php

namespace App\Support\SystemSettings;

use App\Models\Branch;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Horario operativo del negocio. B2 (27/09/2026): cada sucursal puede tener el suyo
 * (`branches.opening_time`/`closing_time`); sin él se usa el general de Configuración.
 * `for($branchId)` devuelve el horario de esa sucursal; sin `for()`, el general.
 *
 * ZEUS-047 (07/10/2026): también los días que abre (`branches.operating_days` o los
 * `booking_open_*` generales). `isWithin()` ya rechaza un día cerrado — así lo respetan solos la
 * cita web, la móvil, el "próximo hueco" y las series recurrentes.
 */
class BusinessHours
{
    private ?int $branchId = null;

    /** Carbon::dayOfWeek (0 = domingo) → clave del ajuste general. */
    public const DAY_SETTING_KEYS = [
        1 => 'booking_open_monday',
        2 => 'booking_open_tuesday',
        3 => 'booking_open_wednesday',
        4 => 'booking_open_thursday',
        5 => 'booking_open_friday',
        6 => 'booking_open_saturday',
        0 => 'booking_open_sunday',
    ];

    public const DAY_NAMES = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 0 => 'domingo'];

    /** @var array{0: ?string, 1: ?string}|null */
    private ?array $branchHours = null;

    /** @var array<int, int>|false|null false = la sucursal no tiene días propios */
    private array|false|null $branchDays = null;

    public function __construct(private SystemSettings $settings) {}

    public function for(?int $branchId): self
    {
        $scoped = clone $this;
        $scoped->branchId = $branchId;
        $scoped->branchHours = null;
        $scoped->branchDays = null;

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

        return $this->isOpenOn($dateTime) && $time >= $this->openingTime() && $time <= $this->closingTime();
    }

    /**
     * Días que abre, en orden lunes→domingo (Carbon::dayOfWeek).
     *
     * @return array<int, int>
     */
    public function operatingDays(): array
    {
        $own = $this->ownDays();
        if ($own !== false) {
            return $own;
        }

        $all = $this->settings->all();

        return array_values(array_filter(
            array_keys(self::DAY_SETTING_KEYS),
            fn (int $day) => (bool) ($all[self::DAY_SETTING_KEYS[$day]] ?? true)
        ));
    }

    public function isOpenOn(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeek, $this->operatingDays(), true);
    }

    /** Motivo por el que no se puede agendar a esa hora, con el texto de siempre para el horario. */
    public function rejectionFor(Carbon $dateTime): ?string
    {
        if (! $this->isOpenOn($dateTime)) {
            return 'El negocio no abre los '.self::DAY_NAMES[$dateTime->dayOfWeek].'s.';
        }

        if (! $this->isWithin($dateTime)) {
            return "La hora elegida está fuera del horario operativo ({$this->openingTime()}–{$this->closingTime()}).";
        }

        return null;
    }

    /** "lunes a sábado", "lunes, miércoles y viernes"… para mostrar en pantallas. */
    public function operatingDaysLabel(): string
    {
        $days = $this->operatingDays();
        $order = array_keys(self::DAY_NAMES);
        $positions = array_map(fn ($d) => array_search($d, $order, true), $days);

        if (count($days) === 7) {
            return 'todos los días';
        }
        if (count($days) > 2 && max($positions) - min($positions) === count($days) - 1) {
            return self::DAY_NAMES[$days[0]].' a '.self::DAY_NAMES[end($days)];
        }

        $names = array_map(fn ($d) => self::DAY_NAMES[$d], $days);
        $last = array_pop($names);

        return $names ? implode(', ', $names).' y '.$last : (string) $last;
    }

    /** @return array<int, int>|false Días propios de la sucursal, o false si usa los generales. */
    private function ownDays(): array|false
    {
        if (! $this->branchId) {
            return false;
        }

        if ($this->branchDays === null) {
            $raw = Branch::whereKey($this->branchId)->value('operating_days');
            $this->branchDays = $raw === null || $raw === ''
                ? false
                : array_values(array_intersect(array_keys(self::DAY_SETTING_KEYS), array_map('intval', explode(',', $raw))));
        }

        return $this->branchDays;
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
