<?php

namespace App\Http\Controllers\Clinical;

use App\Domain\Inventory\Contracts\ItemMovementServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Pet;
use App\Models\PetVaccination;
use App\Models\Service;
use Illuminate\Http\Request;

class PetVaccinationController extends Controller
{
    public function store(Request $request, Pet $pet, ItemMovementServiceInterface $movements)
    {
        $vaccination = $pet->vaccinations()->create($this->validatedData($request));

        // Vacuna aplicada en EstetiCAN con artículo ligado del maestro = consume 1 dosis de
        // inventario. Una vacuna externa (is_external) no descuenta — no se aplicó aquí.
        if ($vaccination->item_id && ! $vaccination->is_external) {
            $movements->record(
                itemId: $vaccination->item_id,
                type: 'consumo_servicio',
                quantity: -1,
                notes: "Vacuna aplicada a {$pet->name} (registro #{$vaccination->id}).",
                reference: $vaccination,
                createdByUserId: auth()->id(),
            );
        }

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Vacuna registrada.')
            ->withFragment('vaccinations');
    }

    public function update(Request $request, Pet $pet, PetVaccination $vaccination)
    {
        $vaccination->update($this->validatedData($request));

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Vacuna actualizada.')
            ->withFragment('vaccinations');
    }

    public function destroy(Pet $pet, PetVaccination $vaccination)
    {
        $vaccination->delete();

        return redirect()
            ->route('clinical.pets.show', $pet)
            ->with('success', 'Vacuna eliminada.')
            ->withFragment('vaccinations');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'item_id' => 'nullable|exists:items,id',
            'is_external' => 'nullable|boolean',
            'applied_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'lot_number' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'administered_by_operator_id' => 'nullable|exists:operators,id',
            'dose_number' => 'nullable|integer|min:1',
            'route' => 'nullable|in:subcutaneous,intramuscular,intranasal,oral',
            'site' => 'nullable|string|max:255',
            'reaction_notes' => 'nullable|string',
        ]);

        // vaccine_name es un snapshot automático del servicio elegido — nunca se teclea a mano,
        // para eliminar el error de dedo que motivó mover esto al catálogo de servicios.
        $validated['vaccine_name'] = Service::findOrFail($validated['service_id'])->name;
        $validated['is_external'] = $request->boolean('is_external');

        // Si se eligió un artículo del maestro y no se capturó fabricante a mano, se auto-llena
        // desde la marca del artículo (mismo patrón snapshot que vaccine_name).
        if (! empty($validated['item_id']) && empty($validated['manufacturer'])) {
            $validated['manufacturer'] = Item::find($validated['item_id'])?->brand;
        }

        return $validated;
    }
}
