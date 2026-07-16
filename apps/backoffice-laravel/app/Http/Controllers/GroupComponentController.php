<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupComponentController extends Controller
{
    public function store(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => 'nullable|required_without:item_id|exists:services,id',
            'item_id' => 'nullable|required_without:service_id|exists:items,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        if (! empty($validated['service_id']) && ! empty($validated['item_id'])) {
            return back()->with('error', 'Elige solo Servicio o Artículo, no ambos.');
        }

        GroupComponent::create([
            'group_id' => $group->id,
            'service_id' => $validated['service_id'] ?? null,
            'item_id' => $validated['item_id'] ?? null,
            'quantity' => $validated['quantity'],
        ]);

        return redirect()->route('groups.edit', $group)->with('success', 'Componente agregado.');
    }

    public function destroy(Group $group, GroupComponent $component): RedirectResponse
    {
        abort_unless($component->group_id === $group->id, 404);

        $component->delete();

        return redirect()->route('groups.edit', $group)->with('success', 'Componente eliminado.');
    }
}
