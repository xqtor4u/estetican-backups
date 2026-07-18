<?php

namespace App\Domain\Inventory\Contracts;

use App\Models\ItemMovement;
use Illuminate\Database\Eloquent\Model;

interface ItemMovementServiceInterface
{
    /**
     * Registra un movimiento de inventario y recalcula el caché `items.stock_quantity`
     * a partir del ledger (SUM de movimientos), bajo lock para evitar condiciones de carrera.
     * Si el movimiento trae `branchId`, también recalcula el saldo cacheado por sucursal en
     * `item_branch_stocks`. Movimientos sin sucursal (ej. consumo automático de vacunas) no
     * generan fila ahí — solo cuentan en el total global.
     *
     * @param  Model|null  $reference  Origen polimórfico opcional (ej. la vacuna que consumió el artículo)
     */
    public function record(
        int $itemId,
        string $type,
        int $quantity,
        ?int $branchId = null,
        ?string $notes = null,
        ?Model $reference = null,
        ?int $createdByUserId = null,
    ): ItemMovement;
}
