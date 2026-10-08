<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ZEUS-047: día inhábil (festivo/cierre). `branch_id` null = todas las sucursales.
 */
#[Fillable(['date', 'branch_id', 'reason'])]
class NonWorkingDay extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Días que aplican a $branchId: los generales más los propios de esa sucursal. */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->where(function (Builder $q) use ($branchId) {
            $q->whereNull('branch_id');
            if ($branchId) {
                $q->orWhere('branch_id', $branchId);
            }
        });
    }

    /** El día inhábil que cubre $date para $branchId, o null si se trabaja. */
    public static function covering(CarbonInterface $date, ?int $branchId): ?self
    {
        return static::query()->forBranch($branchId)->whereDate('date', $date->toDateString())->first();
    }
}
