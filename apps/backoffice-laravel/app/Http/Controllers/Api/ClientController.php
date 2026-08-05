<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Phone;
use App\Support\Search\TokenSearch;
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

        return $query->with('phones')->get()->map(fn (Client $c) => [
            'id'       => $c->id,
            'name'     => $c->full_name,
            'email'    => $c->email,
            'phone'    => $c->phones->first()?->number,
            'pet_count'=> $c->pets()->count(),
        ]);
    }

    public function show(Client $client)
    {
        $client->load('phones', 'pets');

        return [
            'id'         => $client->id,
            'first_name' => $client->first_name,
            'last_name'  => $client->last_name ?? '',
            'apellido_paterno' => $client->apellido_paterno,
            'apellido_materno' => $client->apellido_materno,
            'full_name'  => $client->full_name,
            'email'      => $client->email,
            'address'    => $client->address,
            'city'       => $client->city,
            'state'      => $client->state,
            'zip_code'   => $client->zip_code,
            'notes'      => $client->notes,
            'phones'     => $client->phones->map(fn ($p) => [
                'id'     => $p->id,
                'number' => $p->number,
                'type'   => $p->type,
            ]),
            'pets' => $client->pets->map(fn ($pet) => [
                'id'    => $pet->id,
                'name'  => $pet->name,
                'breed' => $pet->breed,
                'photo' => $pet->profile_photo_path ? Storage::disk('public')->url($pet->profile_photo_path) : null,
            ]),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'email'      => 'nullable|email|max:255',
            'phone'      => 'required|string|max:30',
            'address'    => 'nullable|string|max:255',
            'city'       => 'nullable|string|max:255',
            'state'      => 'nullable|string|max:255',
            'zip_code'   => 'nullable|string|max:20',
            'notes'      => 'nullable|string',
        ]);

        $client = Client::create(collect($data)->except('phone')->all());

        Phone::create([
            'client_id' => $client->id,
            'number'    => $data['phone'],
            'type'      => 'mobile',
        ]);

        return response()->json([
            'id'   => $client->id,
            'name' => $client->full_name,
        ], 201);
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'apellido_paterno' => 'sometimes|nullable|string|max:255',
            'apellido_materno' => 'sometimes|nullable|string|max:255',
            'email'      => 'sometimes|nullable|email|max:255',
            'address'    => 'sometimes|nullable|string|max:255',
            'city'       => 'sometimes|nullable|string|max:255',
            'state'      => 'sometimes|nullable|string|max:255',
            'zip_code'   => 'sometimes|nullable|string|max:20',
            'notes'      => 'sometimes|nullable|string',
            'phones'     => 'sometimes|array',
            'phones.*.number' => 'required_with:phones|string|max:30',
            'phones.*.type'   => 'sometimes|in:mobile,home,work',
        ]);

        $client->update(collect($data)->except('phones')->all());

        if ($request->has('phones')) {
            $client->phones()->delete();
            foreach ($data['phones'] as $p) {
                if (!empty(trim($p['number']))) {
                    $client->phones()->create([
                        'number' => $p['number'],
                        'type'   => $p['type'] ?? 'mobile',
                    ]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
