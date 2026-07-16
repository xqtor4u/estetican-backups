<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Contracts\ItemMovementServiceInterface;
use App\Models\Item;
use App\Models\ItemMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ItemMovementService implements ItemMovementServiceInterface
{
    public function record(
        int $itemId,
        string $type,
        int $quantity,
        ?int $branchId = null,
        ?string $notes = null,
        ?Model $reference = null,
        ?int $createdByUserId = null,
    ): ItemMovement {
        return DB::transaction(function () use ($itemId, $type, $quantity, $branchId, $notes, $reference, $createdByUserId) {
            $item = Item::lockForUpdate()->findOrFail($itemId);

            $movement = ItemMovement::create([
                'item_id' => $item->id,
                'item_name_snapshot' => $item->name,
                'branch_id' => $branchId,
                'type' => $type,
                'quantity' => $quantity,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by_user_id' => $createdByUserId,
            ]);

            $item->stock_quantity = (int) ItemMovement::where('item_id', $item->id)->sum('quantity');
            $item->save();

            return $movement;
        });
    }
}
