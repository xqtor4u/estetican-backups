<?php

namespace App\Models;

use App\Support\CatalogCache\OperatorServiceCapabilityCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plantilla de servicios por rol de puesto: qué servicios trae por defecto quien tenga este
 * rol (SYNC-073). Ver docs/tecnico/SPEC_CAPACIDADES_SERVICIO_POR_OPERADOR.md (Zeus-Estetican).
 */
#[Fillable(['operator_role_id', 'service_id'])]
class OperatorRoleServiceTemplate extends Model
{
    protected $table = 'operator_role_service_template';

    protected static function booted(): void
    {
        static::saved(static fn () => OperatorServiceCapabilityCache::flush());
        static::deleted(static fn () => OperatorServiceCapabilityCache::flush());
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(OperatorRole::class, 'operator_role_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
