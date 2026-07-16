<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Item;
use App\Models\Service;
use App\Support\Pages\GroupsPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD del catálogo de Grupos — combos de Servicios + Artículos con cantidad, para agregar
 * todos sus componentes a una cotización con un clic. Sin capa de dominio: la única lógica
 * real (cálculo de precio) vive en Group::calculatedPrice(), mismo patrón que ItemController.
 */
class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        $groups = Group::query()->withCount('components');

        if ($search !== '') {
            $groups->where('name', 'like', "%{$search}%");
        }

        if ($status === 'active') {
            $groups->where('is_active', true);
        } elseif ($status === 'inactive') {
            $groups->where('is_active', false);
        }

        $groups = $groups->orderByDesc('is_active')->orderBy('name')->paginate(15)->withQueryString();
        $groups->getCollection()->load('components.service', 'components.item');

        $page = GroupsPage::index();

        return view('groups.index', compact('groups', 'search', 'status', 'page'));
    }

    public function create(): View
    {
        $page = GroupsPage::create();

        return view('groups.create', compact('page'));
    }

    public function store(Request $request): RedirectResponse
    {
        $group = Group::create($this->validatedData($request));

        return redirect()->route('groups.edit', $group)->with('success', 'Grupo creado. Ahora agrega sus componentes.');
    }

    public function edit(Group $group): View
    {
        $page = GroupsPage::edit($group);
        $group->load('components.service', 'components.item');
        $services = Service::where('is_active', true)->orderBy('name')->get(['id', 'name', 'price']);
        $items = Item::where('is_active', true)->orderBy('name')->get(['id', 'name', 'price']);

        return view('groups.edit', compact('group', 'page', 'services', 'items'));
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $group->update($this->validatedData($request));

        return redirect()->route('groups.edit', $group)->with('success', 'Grupo actualizado.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $group->delete();

        return redirect()->route('groups.index')->with('success', 'Grupo eliminado.');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        return $validated;
    }
}
