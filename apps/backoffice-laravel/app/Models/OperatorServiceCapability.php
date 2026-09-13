<?php

namespace App\Models;

use App\Support\CatalogCache\OperatorServiceCapabilityCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Capacidad directa de un operador sobre un servicio: alta (`grant`) o baja (`revoke`) manual
 * que el administrador hace sobre la lista efectiva. Un operador tiene a lo sumo una fila por
 * servicio. Ver docs/tecnico/SPEC_CAPACIDADES_SERVICIO_POR_OPERADOR.md (Zeus-Estetican).
 */
#[Fillable(['operator_id', 'service_id', 'mode', 'note', 'created_by_user_id'])]
class OperatorServiceCapability extends Model
{
    public const MODE_GRANT = 'grant';

    public const MODE_REVOKE = 'revoke';

    protected static function booted(): void
    {
        static::saved(static fn () => OperatorServiceCapabilityCache::flush());
        static::deleted(static fn () => OperatorServiceCapabilityCache::flush());
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
