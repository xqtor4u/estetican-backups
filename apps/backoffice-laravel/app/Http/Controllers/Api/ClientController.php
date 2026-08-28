<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Phone;
use App\Rules\ValidPhoneNumber;
use App\Support\Search\TokenSearch;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::orderBy('first_name');

        if ($search = $request->input('search')) {
            TokenSearch::apply($query, $search, [
                'first_name', 'apellido_paterno', 'apellido_materno', 'email', 'phones.number',
            ]);
        }

        $query->with('phones')->withCount('pets');

        $map = fn (Client $c) => [
            'id' => $c->id,
            'name' => $c->full_name,
            'email' => $c->email,
            'phone' => $c->phones->first()?->number,
            'pet_count' => $c->pets_count,
        ];

        // Paginación opcional y retrocompatible: sin `per_page` devuelve el arreglo
        // completo como siempre (la app móvil no cambia); con `per_page` (1-100)
        // devuelve el paginador de Laravel: { data, current_page, per_page, total, last_page, ... }.
        if ($request->filled('per_page')) {
            $perPage = min(max((int) $request->input('per_page'), 1), 100);

            return $query->paginate($perPage)->through($map);
        }

        return $query->get()->map($map);
    }

    public function show(Client $client)
    {
        $client->load('phones', 'pets');

        return [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name ?? '',
            'apellido_paterno' => $client->apellido_paterno,
            'apellido_materno' => $client->apellido_materno,
            'full_name' => $client->full_name,
            'email' => $client->email,
            'address' => $client->address,
            'city' => $client->city,
            'state' => $client->state,
            'zip_code' => $client->zip_code,
            'notes' => $client->notes,
            'phones' => $client->phones->map(fn ($p) => [
                'id' => $p->id,
                'number' => $p->number,
                'extension' => $p->extension,
                'type' => $p->type,
            ]),
            'pets' => $client->pets->map(fn ($pet) => [
                'id' => $pet->id,
                'name' => $pet->name,
                'breed' => $pet->breed,
                'photo' => $pet->profile_photo_path ? Storage::disk('public')->url($pet->profile_photo_path) : null,
            ]),
        ];
    }

    public function store(Request $request, SystemSettings $systemSettings)
    {
        $phoneRule = ValidPhoneNumber::fromSettings($systemSettings);

        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            // 'phone' (forma legada, un solo móvil) sigue soportado por compatibilidad hacia
            // atrás — 'phones' (array, con tipo y orden de importancia) es la forma preferida
            // que ya usa la app móvil actualizada.
            'phone' => ['required_without:phones', 'nullable', 'string', $phoneRule],
            'phones' => 'sometimes|array|min:1',
            'phones.*.number' => ['required_with:phones', 'string', $phoneRule],
            'phones.*.extension' => 'nullable|string|max:10|regex:/^\d{1,10}$/',
            'phones.*.type' => 'sometimes|in:mobile,home,work,other',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $client = Client::create(collect($data)->except(['phone', 'phones'])->all());

        if (! empty($data['phones'])) {
            $order = 0;
            foreach ($data['phones'] as $p) {
                if (trim($p['number'] ?? '') === '') {
                    continue;
                }

                $client->phones()->create([
                    'number' => $p['number'],
                    'extension' => $p['extension'] ?? null,
                    'type' => $p['type'] ?? 'mobile',
                    'sort_order' => $order++,
                ]);
            }
        } else {
            Phone::create([
                'client_id' => $client->id,
                'number' => $data['phone'],
                'type' => 'mobile',
            ]);
        }

        return response()->json([
            'id' => $client->id,
            'name' => $client->full_name,
        ], 201);
    }

    public function update(Request $request, Client $client, SystemSettings $systemSettings)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'apellido_paterno' => 'sometimes|nullable|string|max:255',
            'apellido_materno' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'address' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:255',
            'zip_code' => 'sometimes|nullable|string|max:20',
            'notes' => 'sometimes|nullable|string',
            'phones' => 'sometimes|array',
            'phones.*.number' => ['required_with:phones', 'string', ValidPhoneNumber::fromSettings($systemSettings)],
            'phones.*.extension' => 'nullable|string|max:10|regex:/^\d{1,10}$/',
            'phones.*.type' => 'sometimes|in:mobile,home,work,other',
        ]);

        $client->update(collect($data)->except('phones')->all());

        if ($request->has('phones')) {
            $client->phones()->delete();
            // El array llega en el orden de importancia que el usuario armó en la app (JSON
            // preserva orden) — el índice de recorrido es directamente el sort_order.
            $order = 0;
            foreach ($data['phones'] as $p) {
                if (! empty(trim($p['number']))) {
                    $client->phones()->create([
                        'number' => $p['number'],
                        'extension' => $p['extension'] ?? null,
                        'type' => $p['type'] ?? 'mobile',
                        'sort_order' => $order++,
                    ]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
