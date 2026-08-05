<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\OperatorCheckin;
use App\Support\Search\TokenSearch;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::query()->where('is_active', true)->orderBy('name');

        if ($search = $request->input('search')) {
            TokenSearch::apply($query, $search, ['name', 'department', 'brand', 'presentation']);
        }

        if ($department = $request->input('department')) {
            $query->where('department', $department);
        }

        if ($brand = $request->input('brand')) {
            $query->where('brand', $brand);
        }

        if ($request->has('has_stock')) {
            $this->applyStockFilter($query, $request->boolean('has_stock'), $request->user()->id);
        }

        $items = $query->get();

        return [
            'items' => $items->map(fn (Item $item) => [
                'id'             => $item->id,
                'name'           => $item->name,
                'department'     => $item->department,
                'brand'          => $item->brand,
                'presentation'   => $item->presentation,
                'price'          => $item->price,
                'photo'          => $item->photo_thumbnail_url ?: null,
                'stock_quantity' => $item->stock_quantity,
            ]),
            'departments' => Item::whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department'),
            'brands'      => Item::whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
        ];
    }

    /**
     * Con checkin activo, filtra por existencia en esa sucursal (item_branch_stocks).
     * Sin checkin activo, cae al total global (items.stock_quantity) — decisión explícita
     * del usuario para que el filtro siga siendo útil aunque menos preciso.
     */
    private function applyStockFilter($query, bool $hasStock, int $userId): void
    {
        $checkin = OperatorCheckin::where('user_id', $userId)
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->first();

        if ($checkin) {
            $query->when(
                $hasStock,
                fn ($q) => $q->whereHas('branchStocks', fn ($b) => $b->where('branch_id', $checkin->branch_id)->where('quantity', '>', 0)),
                fn ($q) => $q->whereDoesntHave('branchStocks', fn ($b) => $b->where('branch_id', $checkin->branch_id)->where('quantity', '>', 0)),
            );

            return;
        }

        $query->when(
            $hasStock,
            fn ($q) => $q->where('stock_quantity', '>', 0),
            fn ($q) => $q->where(fn ($qq) => $qq->where('stock_quantity', '<=', 0)->orWhereNull('stock_quantity')),
        );
    }
}
