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

    /**
     * Transfiere existencia entre dos sucursales del mismo artículo — no hay tabla
     * `warehouses`, la "ubicación" ya es `branch_id`. Genera un par de movimientos
     * (`transferencia_salida` en origen, `transferencia_entrada` en destino) dentro
     * de una sola transacción; el de entrada referencia al de salida para poder
     * reconstruir el par. No cambia `items.stock_quantity` (el total es el mismo,
     * solo cambia su distribución entre sucursales).
     *
     * @return array{0: ItemMovement, 1: ItemMovement} [$out, $in]
     */
    public function transfer(
        int $itemId,
        int $fromBranchId,
        int $toBranchId,
        int $quantity,
        ?string $notes = null,
        ?int $createdByUserId = null,
    ): array;
}
