<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\GroupComponent;
use App\Models\Item;
use App\Support\ItemPhotoImageManager;
use App\Support\Pages\ItemsPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * CRUD del maestro de artículos (BL-050) — identidad de producto, venta e inventario
 * (BL-051/BL-049 "IM sencillo"). `stock_quantity` es un caché mantenido por `ItemMovementService`
 * (ver ItemMovementController) — no se edita a mano desde este controller.
 */
class ItemController extends Controller
{
    public function __construct(private readonly ItemPhotoImageManager $imageManager)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        if (! in_array($sort, ['name', 'department', 'brand'], true)) {
            $sort = null;
        }

        $items = Item::query()->withCount('vaccinations');

        if ($search !== '') {
            $items->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('presentation', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $items->where('is_active', true);
        } elseif ($status === 'inactive') {
            $items->where('is_active', false);
        }

        if ($sort === 'name') {
            $items->orderBy('name', $direction);
        } elseif ($sort === 'department') {
            $items->orderBy('department', $direction)->orderBy('name');
        } elseif ($sort === 'brand') {
            $items->orderBy('brand', $direction)->orderBy('name');
        } else {
            $items->orderByDesc('is_active')->orderBy('name');
        }

        $items = $items->paginate(15)->withQueryString();

        $page = ItemsPage::index();

        return view('items.index', compact('items', 'search', 'status', 'sort', 'direction', 'page'));
    }

    public function create(): View
    {
        $page = ItemsPage::create();

        return view('items.create', compact('page'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $returnToPet = $request->integer('return_to_pet');
        $newPhotoPath = null;

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('photo'));
            $validated['photo_path'] = $newPhotoPath;
        }

        try {
            Item::create($validated);
        } catch (Throwable $exception) {
            $this->imageManager->deleteFiles($newPhotoPath);

            throw $exception;
        }

        if ($returnToPet) {
            return redirect()->route('clinical.pets.show', $returnToPet)
                ->with('success', 'Artículo creado.')
                ->withFragment('vaccinations');
        }

        return redirect()->route('items.index')->with('success', 'Artículo creado.');
    }

    public function edit(Item $item): View
    {
        $page = ItemsPage::edit($item);
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $movements = $item->movements()->with('branch:id,name')->latest()->limit(20)->get();

        return view('items.edit', compact('item', 'page', 'branches', 'movements'));
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $oldPhotoPath = $item->photo_path;
        $newPhotoPath = null;

        if ($request->boolean('remove_photo')) {
            $validated['photo_path'] = null;
        }

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('photo'));
            $validated['photo_path'] = $newPhotoPath;
        }

        try {
            $item->update($validated);
        } catch (Throwable $exception) {
            $this->imageManager->deleteFiles($newPhotoPath);

            throw $exception;
        }

        if ($oldPhotoPath && $oldPhotoPath !== $item->photo_path) {
            $this->imageManager->deleteFiles($oldPhotoPath);
        }

        return redirect()->route('items.index')->with('success', 'Artículo actualizado.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        $groupCount = GroupComponent::where('item_id', $item->id)->count();

        if ($groupCount > 0) {
            return redirect()->route('items.index')
                ->with('error', "No se puede eliminar: es componente de {$groupCount} grupo(s). Quítalo del/de los grupo(s) primero.");
        }

        $this->imageManager->deleteFiles($item->photo_path);
        $item->delete();

        return redirect()->route('items.index')->with('success', 'Artículo eliminado.');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'presentation' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'ai_visible' => 'nullable|boolean',
            'photo' => 'nullable|image|max:10240',
            'notes' => 'nullable|string',
        ]);

        unset($validated['photo']);

        // Alta rápida (mini-formulario en vacunas) no manda checkbox de "activo" — se asume activo.
        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        // Visible al asistente IA solo si se marca a mano — nada se expone por default.
        $validated['ai_visible'] = $request->boolean('ai_visible');

        return $validated;
    }
}
