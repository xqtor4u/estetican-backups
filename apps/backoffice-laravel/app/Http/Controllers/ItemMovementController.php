<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Contracts\ItemMovementServiceInterface;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Registro manual de movimientos de inventario (entrada/ajuste/pérdida/transferencia)
 * sobre el maestro de artículos — cimiento del IM sencillo (BL-049). El consumo
 * automático por uso en servicio (ej. vacunas, citas completadas) se dispara desde
 * otros controllers, no desde aquí.
 */
class ItemMovementController extends Controller
{
    private const MANUAL_TYPES = ['entrada', 'ajuste', 'perdida'];

    public function store(Request $request, Item $item, ItemMovementServiceInterface $movements): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:'.implode(',', self::MANUAL_TYPES),
            'quantity' => 'required|integer|min:1',
            'branch_id' => 'required|exists:branches,id',
            'notes' => 'nullable|string',
        ]);

        // Solo "ajuste" puede restar (corrección manual); entrada siempre suma, pérdida siempre resta.
        $signedQuantity = match ($validated['type']) {
            'entrada' => $validated['quantity'],
            'perdida' => -$validated['quantity'],
            'ajuste' => $request->input('direction') === 'resta' ? -$validated['quantity'] : $validated['quantity'],
        };

        $movements->record(
            itemId: $item->id,
            type: $validated['type'],
            quantity: $signedQuantity,
            branchId: $validated['branch_id'] ?? null,
            notes: $validated['notes'] ?? null,
            createdByUserId: auth()->id(),
        );

        return redirect()->route('items.edit', $item)->with('success', 'Movimiento registrado.');
    }

    public function transfer(Request $request, Item $item, ItemMovementServiceInterface $movements): RedirectResponse
    {
        $validated = $request->validate([
            'from_branch_id' => 'required|exists:branches,id|different:to_branch_id',
            'to_branch_id' => 'required|exists:branches,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $movements->transfer(
            itemId: $item->id,
            fromBranchId: (int) $validated['from_branch_id'],
            toBranchId: (int) $validated['to_branch_id'],
            quantity: (int) $validated['quantity'],
            notes: $validated['notes'] ?? null,
            createdByUserId: auth()->id(),
        );

        return redirect()->route('items.edit', $item)->with('success', 'Transferencia registrada.');
    }
}
