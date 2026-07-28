<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $viewMode = $request->query('view') === 'table' ? 'table' : 'blocks';
        $search = trim((string) $request->query('search', ''));
        $petCreationMode = $request->query('mode') === 'pet_creation';
        $petsFilter = $request->query('pets_filter');
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (! in_array($petsFilter, ['all', 'with_pets', 'without_pets'], true)) {
            $petsFilter = 'all';
        }

        if (! in_array($sort, ['name', 'first_name', 'last_name', 'email', 'pets'], true)) {
            $sort = null;
        }

        $clients = Client::query()
            ->select(['id', 'first_name', 'apellido_paterno', 'apellido_materno', 'email'])
            ->with([
                'addresses:id,client_id,type,street,exterior_number,interior_number,colonia,city,state,zip,country',
                'phones:id,client_id,number,type',
                'pets' => fn ($query) => $query
                    ->select(['id', 'client_id', 'name', 'species', 'breed', 'birth_date', 'death_date', 'microchip_code'])
                    ->whereNull('death_date')
                    ->visible()
                    ->orderBy('name')
                    ->with(['primaryPhoto', 'latestPhoto']),
            ])
            ->withCount([
                'pets as live_pets_count' => fn ($query) => $query->whereNull('death_date')->visible(),
            ]);

        if ($search !== '') {
            $clients->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('apellido_paterno', 'like', "%{$search}%")
                    ->orWhere('apellido_materno', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('phones', function ($phoneQuery) use ($search) {
                        $phoneQuery->where('number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('pets', function ($petQuery) use ($search) {
                        $petQuery->whereNull('death_date')
                            ->where(function ($nestedPetQuery) use ($search) {
                                $nestedPetQuery->where('name', 'like', "%{$search}%")
                                    ->orWhere('breed', 'like', "%{$search}%")
                                    ->orWhere('species', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($petsFilter === 'with_pets') {
            $clients->whereHas('pets', fn ($query) => $query->whereNull('death_date'));
        } elseif ($petsFilter === 'without_pets') {
            $clients->whereDoesntHave('pets', fn ($query) => $query->whereNull('death_date'));
        }

        if ($sort === 'name' || $sort === 'first_name') {
            $clients->orderBy('first_name', $direction)
                ->orderBy('apellido_paterno', $direction)
                ->orderBy('apellido_materno', $direction);
        } elseif ($sort === 'last_name') {
            $clients->orderBy('apellido_paterno', $direction)
                ->orderBy('apellido_materno', $direction)
                ->orderBy('first_name', $direction);
        } elseif ($sort === 'email') {
            $clients->orderBy('email', $direction)
                ->orderBy('apellido_paterno')
                ->orderBy('apellido_materno')
                ->orderBy('first_name');
        } elseif ($sort === 'pets') {
            $clients->orderBy('live_pets_count', $direction)
                ->orderBy('apellido_paterno')
                ->orderBy('apellido_materno')
                ->orderBy('first_name');
        } else {
            $clients->orderBy('apellido_paterno', $direction)
                ->orderBy('apellido_materno', $direction)
                ->orderBy('first_name', $direction);
        }

        $clients = $clients->paginate(10)->withQueryString();

        return view('clients.index', compact('clients', 'viewMode', 'search', 'petsFilter', 'sort', 'direction', 'petCreationMode'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(SystemSettings $systemSettings)
    {
        return view('clients.create', [
            'suggestAreaCode' => $systemSettings->all()['commercial_clients_suggest_area_code'] ?? false,
            'defaultAreaCode' => $systemSettings->all()['commercial_clients_default_area_code'] ?? '',
            'defaultSpecies' => $systemSettings->all()['commercial_pets_default_species'] ?? '',
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',

            'email' => 'nullable|email|unique:clients,email',
            'next_action' => 'nullable|string|in:save,schedule_first_pet',
            'addresses' => 'nullable|array',
            'addresses.*.type' => 'required|string',
            'addresses.*.street' => 'required|string',
            'addresses.*.exterior_number' => 'nullable|string|max:255',
            'addresses.*.interior_number' => 'nullable|string|max:255',
            'addresses.*.colonia' => 'nullable|string',
            'addresses.*.city' => 'required|string',
            'addresses.*.state' => 'nullable|string',
            'addresses.*.zip' => 'nullable|string|max:255',
            'addresses.*.country' => 'required|string',
            'addresses.*.lat' => 'nullable|numeric|between:-90,90',
            'addresses.*.lng' => 'nullable|numeric|between:-180,180',
            'phones' => 'required|array|min:1',
            'phones.*.number' => 'required|string',
            'phones.*.type' => 'required|string',
            'pets' => 'nullable|array',
            'pets.*.name' => 'required|string|max:255',
            'pets.*.species' => 'nullable|string|max:255',
            'pets.*.breed' => 'nullable|string|max:255',
            'pets.*.birth_date' => 'nullable|date',
            'pets.*.death_date' => 'nullable|date|after_or_equal:pets.*.birth_date',
            'pets.*.microchip_code' => 'nullable|string|max:255',
            'pets.*.tattoo_code' => 'nullable|string|max:255',
            'pets.*.sex' => 'nullable|in:male,female,unknown',
            'pets.*.coat_color' => 'nullable|string|max:255',
            'pets.*.size' => 'nullable|in:mini,small,medium,large,giant',
            'pets.*.is_sterilized' => 'nullable|boolean',
            'pets.*.notes' => 'nullable|string',
        ]);

        if ($this->countActivePhones($validated['phones'] ?? []) === 0) {
            return back()
                ->withErrors(['phones' => 'Debe capturarse al menos un telefono activo para el cliente.'])
                ->withInput();
        }

        $nextAction = (string) ($validated['next_action'] ?? 'save');

        if ($nextAction === 'schedule_first_pet' && $this->countActivePets($validated['pets'] ?? []) === 0) {
            return back()
                ->withErrors(['pets' => 'Debes capturar al menos una mascota para continuar directo a agenda.'])
                ->withInput();
        }

        $client = Client::create($request->only(['first_name', 'apellido_paterno', 'apellido_materno', 'email', 'address', 'city', 'state', 'zip_code', 'notes']));

        if ($request->addresses) {
            foreach ($request->addresses as $addressData) {
                $addressData['client_id'] = $client->id;
                $client->addresses()->create($this->prepareAddressData($addressData, $client->id));
            }
        }

        if ($request->phones) {
            foreach ($request->phones as $phoneData) {
                $phoneData['client_id'] = $client->id;
                $client->phones()->create($phoneData);
            }
        }

        if ($request->pets) {
            foreach ($request->pets as $petData) {
                $client->pets()->create($this->preparePetData($petData, $client->id));
            }
        }

        if ($nextAction === 'schedule_first_pet') {
            $firstPet = $client->pets()->orderBy('id')->first();

            if ($firstPet) {
                return redirect()
                    ->route('pets.bookings.create', [$firstPet])
                    ->with('success', 'Cliente creado. Continúa con la programación del servicio para la primera mascota capturada.');
            }

        }

        return redirect()->route('clients.index')->with('success', 'Cliente creado.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        $client->load([
            'addresses:id,client_id,type,street,exterior_number,interior_number,colonia,city,state,zip,country,lat,lng',
            'phones:id,client_id,number,type',
            'pets' => fn ($query) => $query
                ->select(['id', 'client_id', 'name', 'species', 'breed', 'birth_date', 'death_date', 'microchip_code'])
                ->whereNull('death_date')
                ->orderBy('name')
                ->with(['primaryPhoto', 'latestPhoto']),
        ]);

        return view('clients.show', compact('client'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client, SystemSettings $systemSettings)
    {
        $client->load([
            'pets' => fn ($query) => $query
                ->orderByRaw('death_date is not null')
                ->orderBy('name')
                ->with(['primaryPhoto', 'latestPhoto']),
        ]);

        return view('clients.edit', [
            'client' => $client,
            'defaultSpecies' => $systemSettings->all()['commercial_pets_default_species'] ?? '',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:clients,email,'.$client->id,
            'receives_offers' => 'nullable|boolean',
            'receives_service_reminders' => 'nullable|boolean',
            'receives_job_updates' => 'nullable|boolean',
            'receives_account_statements' => 'nullable|boolean',
            'receives_other_notifications' => 'nullable|boolean',
            'addresses' => 'nullable|array',
            'addresses.*.type' => 'required|string',
            'addresses.*.street' => 'required|string',
            'addresses.*.exterior_number' => 'nullable|string|max:255',
            'addresses.*.interior_number' => 'nullable|string|max:255',
            'addresses.*.colonia' => 'nullable|string',
            'addresses.*.city' => 'required|string',
            'addresses.*.state' => 'nullable|string',
            'addresses.*.zip' => 'nullable|string|max:255',
            'addresses.*.country' => 'required|string',
            'addresses.*.lat' => 'nullable|numeric|between:-90,90',
            'addresses.*.lng' => 'nullable|numeric|between:-180,180',
            'phones' => 'required|array|min:1',
            'phones.*.number' => 'required|string',
            'phones.*.type' => 'required|string',
            'pets' => 'nullable|array',
            'pets.*.name' => 'required|string|max:255',
            'pets.*.species' => 'nullable|string|max:255',
            'pets.*.breed' => 'nullable|string|max:255',
            'pets.*.birth_date' => 'nullable|date',
            'pets.*.death_date' => 'nullable|date|after_or_equal:pets.*.birth_date',
            'pets.*.microchip_code' => 'nullable|string|max:255',
            'pets.*.tattoo_code' => 'nullable|string|max:255',
            'pets.*.sex' => 'nullable|in:male,female,unknown',
            'pets.*.coat_color' => 'nullable|string|max:255',
            'pets.*.size' => 'nullable|in:mini,small,medium,large,giant',
            'pets.*.is_sterilized' => 'nullable|boolean',
            'pets.*.notes' => 'nullable|string',
        ]);

        if ($this->countActivePhones($validated['phones'] ?? []) === 0) {
            return back()
                ->withErrors(['phones' => 'Debe capturarse al menos un telefono activo para el cliente.'])
                ->withInput();
        }

        $client->update($request->only([
            'first_name', 'apellido_paterno', 'apellido_materno', 'email', 'address', 'city', 'state', 'zip_code', 'notes',
            'receives_offers', 'receives_service_reminders', 'receives_job_updates',
            'receives_account_statements', 'receives_other_notifications',
        ]));

        // Direcciones
        if ($request->addresses) {
            foreach ($request->addresses as $addressData) {
                if (! empty($addressData['delete']) && ! empty($addressData['id'])) {
                    $client->addresses()->where('id', $addressData['id'])->delete();

                    continue;
                }

                if (empty($addressData['delete'])) {
                    $client->addresses()->updateOrCreate(
                        ['id' => $addressData['id'] ?? null],
                        $this->prepareAddressData($addressData, $client->id)
                    );
                }
            }
        }

        // Teléfonos
        if ($request->phones) {
            foreach ($request->phones as $phoneData) {
                if (! empty($phoneData['delete']) && ! empty($phoneData['id'])) {
                    $client->phones()->where('id', $phoneData['id'])->delete();

                    continue;
                }

                if (empty($phoneData['delete'])) {
                    $phoneData['client_id'] = $client->id;
                    $client->phones()->updateOrCreate(
                        ['id' => $phoneData['id'] ?? null],
                        $phoneData
                    );
                }
            }
        }

        if ($request->pets) {
            foreach ($request->pets as $petData) {
                if (! empty($petData['delete']) && ! empty($petData['id'])) {
                    $client->pets()->where('id', $petData['id'])->delete();

                    continue;
                }

                if (empty($petData['delete'])) {
                    $petId = ! empty($petData['id']) ? (int) $petData['id'] : null;
                    if ($petId) {
                        $client->pets()->where('id', $petId)->update(
                            $this->preparePetData($petData, $client->id)
                        );
                    } else {
                        $client->pets()->create(
                            $this->preparePetData($petData, $client->id)
                        );
                    }
                }
            }
        }

        return redirect()->route('clients.edit', $client->id)->with('success', 'Cliente actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Cliente eliminado.');
    }

    private function preparePetData(array $petData, int $clientId): array
    {
        return [
            'client_id' => $clientId,
            'name' => $petData['name'],
            'species' => $petData['species'] ?? null,
            'breed' => $petData['breed'] ?? null,
            'birth_date' => $petData['birth_date'] ?? null,
            'death_date' => $petData['death_date'] ?? null,
            'microchip_code' => $petData['microchip_code'] ?? null,
            'tattoo_code' => $petData['tattoo_code'] ?? null,
            'sex' => $petData['sex'] ?? null,
            'coat_color' => $petData['coat_color'] ?? null,
            'size' => $petData['size'] ?? null,
            'is_sterilized' => ! empty($petData['is_sterilized']),
            'notes' => $petData['notes'] ?? null,
        ];
    }

    private function prepareAddressData(array $addressData, int $clientId): array
    {
        return [
            'client_id' => $clientId,
            'type' => $addressData['type'],
            'street' => $this->nullableString($addressData['street'] ?? null),
            'exterior_number' => $this->nullableString($addressData['exterior_number'] ?? null),
            'interior_number' => $this->nullableString($addressData['interior_number'] ?? null),
            'colonia' => $this->nullableString($addressData['colonia'] ?? null),
            'city' => $this->nullableString($addressData['city'] ?? null),
            'state' => $this->nullableString($addressData['state'] ?? null),
            'zip' => $this->nullableString($addressData['zip'] ?? null),
            'country' => $this->nullableString($addressData['country'] ?? null) ?? 'México',
            'lat' => $addressData['lat'] ?? null,
            'lng' => $addressData['lng'] ?? null,
        ];
    }

    private function countActivePhones(array $phones): int
    {
        return collect($phones)
            ->filter(fn (array $phoneData): bool => empty($phoneData['delete']) && $this->nullableString($phoneData['number'] ?? null) !== null)
            ->count();
    }

    private function countActivePets(array $pets): int
    {
        return collect($pets)
            ->filter(fn (array $petData): bool => empty($petData['delete']) && $this->nullableString($petData['name'] ?? null) !== null)
            ->count();
    }

    private function nullableString(?string $value): ?string
    {
        $normalized = is_string($value) ? trim($value) : '';

        return $normalized === '' ? null : $normalized;
    }
}
