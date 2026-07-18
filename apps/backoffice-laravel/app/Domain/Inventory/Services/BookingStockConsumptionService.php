<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Contracts\BookingStockConsumptionServiceInterface;
use App\Domain\Inventory\Contracts\ItemMovementServiceInterface;
use App\Models\ItemMovement;
use App\Models\SpaBooking;
use App\Models\SpaBookingItem;

class BookingStockConsumptionService implements BookingStockConsumptionServiceInterface
{
    public function __construct(private ItemMovementServiceInterface $itemMovements) {}

    public function consume(SpaBooking $booking, ?int $createdByUserId = null): void
    {
        $booking->loadMissing(['items', 'operator.branches']);
        $branchId = $booking->operator?->primaryBranch()?->id;

        foreach ($booking->items as $bookingItem) {
            if (ItemMovement::where('reference_type', SpaBookingItem::class)
                ->where('reference_id', $bookingItem->id)
                ->where('type', 'consumo_servicio')
                ->exists()) {
                continue;
            }

            // `item_movements.quantity` es integer; `spa_booking_items.quantity` es decimal:2
            // (permite fracciones de servicio). Se redondea al descontar del ledger.
            $quantity = (int) round((float) $bookingItem->quantity);

            if ($quantity <= 0) {
                continue;
            }

            $this->itemMovements->record(
                itemId: $bookingItem->item_id,
                type: 'consumo_servicio',
                quantity: -$quantity,
                branchId: $branchId,
                notes: "Consumo por cita #{$booking->id} completada.",
                reference: $bookingItem,
                createdByUserId: $createdByUserId,
            );
        }
    }
}
