<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Pet;
use App\Models\Vehicle;
use App\Support\Pages\MapaZonasPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MapaZonasController extends Controller
{
    public function index(): View
    {
        $branches = Branch::whereNotNull('lat')->whereNotNull('lng')
            ->get(['id', 'name', 'lat', 'lng'])
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'label' => $b->name,
                'lat' => (float) $b->lat,
                'lng' => (float) $b->lng,
            ])->values();

        $clients = Address::with('client')->whereNotNull('lat')->whereNotNull('lng')->get()
            ->map(fn (Address $a) => [
                'id' => $a->id,
                'label' => $a->client?->full_name ?: 'Cliente',
                'lat' => (float) $a->lat,
                'lng' => (float) $a->lng,
            ])->values();

        $pets = Pet::visible()->with('client')->whereNotNull('lat')->whereNotNull('lng')->get()
            ->map(fn (Pet $p) => [
                'id' => $p->id,
                'label' => trim($p->name.' — '.($p->client?->full_name ?: 'Cliente')),
                'lat' => (float) $p->lat,
                'lng' => (float) $p->lng,
            ])->values();

        $vehicles = Vehicle::where('is_active', true)->whereNotNull('lat')->whereNotNull('lng')->get()
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'label' => $v->name,
                'lat' => (float) $v->lat,
                'lng' => (float) $v->lng,
            ])->values();

        $unlocatedPets = Pet::visible()
            ->with('client')
            ->where(fn ($q) => $q->whereNull('lat')->orWhereNull('lng'))
            ->orderBy('name')
            ->get(['id', 'name', 'client_id'])
            ->map(fn (Pet $p) => [
                'id' => $p->id,
                'label' => trim($p->name.' — '.($p->client?->full_name ?: 'Cliente')),
            ])->values();

        return view('mapa-zonas.index', [
            'page' => MapaZonasPage::index(),
            'branches' => $branches,
            'clients' => $clients,
            'pets' => $pets,
            'vehicles' => $vehicles,
            'unlocatedPets' => $unlocatedPets,
        ]);
    }

    public function updatePetLocation(Request $request, Pet $pet): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $pet->update($validated);

        return response()->json([
            'id' => $pet->id,
            'label' => trim($pet->name.' — '.($pet->client?->full_name ?: 'Cliente')),
            'lat' => (float) $pet->lat,
            'lng' => (float) $pet->lng,
        ]);
    }

    public function storeVehicle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string'],
        ]);

        $vehicle = Vehicle::create($validated);

        return response()->json([
            'id' => $vehicle->id,
            'label' => $vehicle->name,
            'lat' => (float) $vehicle->lat,
            'lng' => (float) $vehicle->lng,
        ]);
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'lat' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string'],
        ]);

        $vehicle->update($validated);

        return response()->json([
            'id' => $vehicle->id,
            'label' => $vehicle->name,
            'lat' => (float) $vehicle->lat,
            'lng' => (float) $vehicle->lng,
        ]);
    }

    public function destroyVehicle(Vehicle $vehicle): JsonResponse
    {
        $vehicle->delete();

        return response()->json(['deleted' => true]);
    }
}
