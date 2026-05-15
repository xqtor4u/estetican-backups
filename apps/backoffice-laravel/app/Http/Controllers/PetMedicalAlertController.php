<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Pet;
use App\Models\PetMedicalAlert;
use Illuminate\Http\Request;

class PetMedicalAlertController extends Controller
{
    public function store(Request $request, Client $client, Pet $pet)
    {
        $pet->medicalAlerts()->create($this->validatedData($request));

        return redirect()
            ->route('clients.pets.show', [$client, $pet])
            ->with('success', 'Alerta medica creada.')
            ->withFragment('medical-alerts');
    }

    public function update(Request $request, Client $client, Pet $pet, PetMedicalAlert $medicalAlert)
    {
        $medicalAlert->update($this->validatedData($request));

        return redirect()
            ->route('clients.pets.show', [$client, $pet])
            ->with('success', 'Alerta medica actualizada.')
            ->withFragment('medical-alerts');
    }

    public function destroy(Client $client, Pet $pet, PetMedicalAlert $medicalAlert)
    {
        $medicalAlert->delete();

        return redirect()
            ->route('clients.pets.show', [$client, $pet])
            ->with('success', 'Alerta medica eliminada.')
            ->withFragment('medical-alerts');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'severity' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        return [
            'category' => $validated['category'],
            'description' => $validated['description'],
            'severity' => $validated['severity'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}