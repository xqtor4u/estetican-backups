<?php

namespace App\Domain\Inventory\Contracts;

use App\Models\SpaBooking;

interface BookingStockConsumptionServiceInterface
{
    /**
     * Descuenta del inventario los `items` (`SpaBookingItem`) de una cita al completarse,
     * en la sucursal primaria del operador asignado. Idempotente: una línea que ya generó
     * su movimiento de consumo no vuelve a descontarse si la cita se re-completa.
     */
    public function consume(SpaBooking $booking, ?int $createdByUserId = null): void;
}
