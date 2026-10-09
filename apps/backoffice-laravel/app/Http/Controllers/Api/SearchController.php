<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\Search\TokenSearch;
use App\Support\WhatsApp\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ZEUS-032 (Fase 5 "Mínimo de Clicks Posible"): búsqueda universal — mascota, cliente, teléfono
 * o cita en un solo cuadro, en vez de tres pantallas de búsqueda independientes
 * (`Api\PetController::index()`, `Api\ClientController::index()`, ninguna de citas). No hay
 * lógica de matching nueva: cada grupo reusa `TokenSearch::apply()` tal cual ya la usan esos dos
 * controllers, solo que las tres corren en un solo request.
 *
 * Cada grupo se gatea por el mismo permiso que ya protege su listado individual — un operador sin
 * uno de los tres permisos simplemente no recibe ese grupo (`[]`), no un 403 parcial.
 */
class SearchController extends Controller
{
    private const LIMIT = 5;

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1'],
        ]);
        $q = $validated['q'];
        $user = $request->user();

        return [
            'pets' => $user->can('ver mascotas') ? $this->searchPets($q) : [],
            'clients' => $user->can('ver clientes') ? $this->searchClients($q) : [],
            'bookings' => $user->can('ver agenda') ? $this->searchBookings($q, $user) : [],
        ];
    }

    private function searchPets(string $q)
    {
        $query = Pet::visible()->with('client.phones');

        TokenSearch::apply($query, $q, [
            'name', 'breed', 'client.first_name', 'client.apellido_paterno',
            'client.apellido_materno', 'client.phones.number',
        ]);

        return $query->orderBy('name')->limit(self::LIMIT)->get()->map(fn (Pet $pet) => [
            'id' => $pet->id,
            'name' => $pet->name,
            'breed' => $pet->breed,
            'photo' => $pet->profile_photo_path
                ? Storage::disk('public')->url($pet->profile_photo_path)
                : null,
            'client' => $pet->client ? [
                'id' => $pet->client->id,
                'name' => $pet->client->full_name,
                'phone' => PhoneNormalizer::bestPhoneFor($pet->client),
            ] : null,
        ])->values();
    }

    private function searchClients(string $q)
    {
        $query = Client::with('phones')->withCount('pets');

        TokenSearch::apply($query, $q, [
            'first_name', 'apellido_paterno', 'apellido_materno', 'email', 'phones.number',
        ]);

        return $query->orderBy('first_name')->limit(self::LIMIT)->get()->map(fn (Client $c) => [
            'id' => $c->id,
            'name' => $c->full_name,
            'phone' => PhoneNormalizer::bestPhoneFor($c),
            'pet_count' => $c->pets_count,
        ])->values();
    }

    private function searchBookings(string $q, User $user)
    {
        $query = SpaBooking::visibleTo($user)->with(['pet.client', 'services.service']);

        TokenSearch::apply($query, $q, [
            'pet.name', 'pet.client.first_name', 'pet.client.apellido_paterno',
            'pet.client.apellido_materno', 'pet.client.phones.number', 'services.service.name',
        ]);

        return $query->orderByDesc('scheduled_at')->limit(self::LIMIT)->get()->map(fn (SpaBooking $b) => [
            'id' => $b->id,
            'date_label' => $b->scheduled_at->translatedFormat('D j M'),
            'time' => $b->scheduled_at->format('H:i'),
            'status' => $b->status,
            'pet' => $b->pet ? ['id' => $b->pet->id, 'name' => $b->pet->name] : null,
            'services' => $b->services->map(fn ($s) => ['name' => $s->service?->name ?? '—'])->values(),
        ])->values();
    }
}
