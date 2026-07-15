<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\HotelReservation;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Support\CatalogCache\PetCatalogCache;
use App\Support\PetPhotoImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PetController extends Controller
{
    public function index(Request $request)
    {
        $viewMode = $request->query('view') === 'table' ? 'table' : 'blocks';
        $search = trim((string) $request->query('search', ''));
        $species = trim((string) $request->query('species', ''));
        $status = $request->query('status');
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (! in_array($status, ['all', 'active', 'deceased'], true)) {
            $status = 'all';
        }

        if (! in_array($sort, ['name', 'client', 'species', 'status'], true)) {
            $sort = null;
        }

        $pets = Pet::query()
            ->select([
                'pets.id',
                'pets.client_id',
                'pets.name',
                'pets.species',
                'pets.breed',
                'pets.birth_date',
                'pets.death_date',
                'pets.microchip_code',
                'pets.notes',
            ])
            ->addSelect([
                'last_spa_at' => SpaBooking::select('scheduled_at')
                    ->whereColumn('pet_id', 'pets.id')
                    ->where('scheduled_at', '<=', now())
                    ->latest('scheduled_at')
                    ->limit(1),
                'last_hotel_at' => HotelReservation::select('start_at')
                    ->whereColumn('pet_id', 'pets.id')
                    ->where('start_at', '<=', now())
                    ->latest('start_at')
                    ->limit(1),
            ])
            ->with([
                'client:id,first_name,apellido_paterno,apellido_materno,email',
                'primaryPhoto',
                'latestPhoto',
            ]);

        if ($search !== '') {
            $pets->where(function ($query) use ($search) {
                $query->where('pets.name', 'like', "%{$search}%")
                    ->orWhere('pets.breed', 'like', "%{$search}%")
                    ->orWhere('pets.species', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('apellido_paterno', 'like', "%{$search}%")
                            ->orWhere('apellido_materno', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($species !== '') {
            $pets->where('pets.species', $species);
        }

        if ($status === 'active') {
            $pets->whereNull('pets.death_date');
        } elseif ($status === 'deceased') {
            $pets->whereNotNull('pets.death_date');
        }

        if ($sort === 'client') {
            $pets->join('clients', 'clients.id', '=', 'pets.client_id')
                ->orderBy('clients.first_name', $direction)
                ->orderBy('clients.apellido_paterno', $direction)
                ->orderBy('clients.apellido_materno', $direction)
                ->orderBy('pets.name');
        } elseif ($sort === 'species') {
            $pets->orderByRaw('case when pets.species is null or pets.species = "" then 1 else 0 end')
                ->orderBy('pets.species', $direction)
                ->orderBy('pets.name');
        } elseif ($sort === 'status') {
            $pets->orderByRaw(
                $direction === 'asc'
                    ? 'case when pets.death_date is null then 0 else 1 end asc'
                    : 'case when pets.death_date is null then 0 else 1 end desc'
            )->orderBy('pets.name');
        } elseif ($sort === 'name') {
            $pets->orderBy('pets.name', $direction)
                ->orderByRaw('pets.death_date is not null');
        } else {
            $pets->orderByRaw('pets.death_date is not null')
                ->orderBy('pets.name');
        }

        $speciesOptions = PetCatalogCache::speciesOptions();

        $pets = $pets
            ->paginate(12)
            ->withQueryString();

        return view('pets.index', compact(
            'pets',
            'viewMode',
            'search',
            'species',
            'speciesOptions',
            'status',
            'sort',
            'direction',
        ));
    }

    public function catalogShow(Request $request, Pet $pet)
    {
        $viewMode = $request->query('view') === 'table' ? 'table' : 'blocks';

        $client = $pet->client;

        return $this->renderPetShow($client, $pet, true, $viewMode);
    }

    public function show(Request $request, Client $client, Pet $pet)
    {
        return $this->renderPetShow($client, $pet, false, $request->query('view') === 'table' ? 'table' : 'blocks');
    }

    public function update(Request $request, Pet $pet)
    {
        $pet->update($this->validatedPetData($request));

        $viewMode = $request->input('return_view_mode') === 'table' ? 'table' : 'blocks';

        return redirect()
            ->route('pets.show', ['pet' => $pet, 'view' => $viewMode])
            ->with('success', 'Mascota actualizada.')
            ->withFragment('core-profile');
    }

    public function updateFromClient(Request $request, Client $client, Pet $pet)
    {
        $pet->update($this->validatedPetData($request));

        return redirect()
            ->route('clients.pets.show', [$client, $pet])
            ->with('success', 'Mascota actualizada.')
            ->withFragment('core-profile');
    }

    public function updateOwner(Request $request, Pet $pet)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
        ]);

        if ((int) $validated['client_id'] === $pet->client_id) {
            return redirect()->route('pets.show', $pet)->with('success', 'El dueño ya era ese cliente.');
        }

        $pet->update(['client_id' => $validated['client_id']]);

        return redirect()
            ->route('pets.show', $pet)
            ->with('success', 'Dueño de la mascota actualizado.');
    }

    private function renderPetShow(Client $client, Pet $pet, bool $isRootView, string $returnViewMode)
    {
        $client->load([
            'pets' => fn ($query) => $query
                ->select(['id', 'client_id', 'name', 'species', 'death_date'])
                ->orderByRaw('death_date is not null')
                ->orderBy('name'),
        ]);

        $pet->load([
            'medicalAlerts' => fn ($query) => $query
                ->select(['id', 'pet_id', 'category', 'description', 'severity', 'notes', 'is_active', 'created_at', 'updated_at'])
                ->orderByDesc('is_active')
                ->latest(),
            'photos' => fn ($query) => $query
                ->select(['id', 'pet_id', 'photo_url', 'photo_type', 'taken_at', 'description', 'is_primary', 'created_at'])
                ->orderByDesc('is_primary')
                ->orderByDesc('taken_at')
                ->latest(),
            'spaBookings' => fn ($query) => $query
                ->with(['services.service:id,code,name,type,suggested_duration_minutes', 'pet.client:id,first_name,apellido_paterno,apellido_materno'])
                ->where('scheduled_at', '>=', now()->startOfDay())
                ->orderBy('scheduled_at')
                ->limit(5),
        ]);

        $clinicalModuleEnabled = (bool) app(\App\Support\SystemSettings\SystemSettings::class)->all()['clinical_module_enabled'];

        if ($clinicalModuleEnabled) {
            $pet->load(['vaccinations' => fn ($query) => $query->with('service:id,name')->orderByDesc('expires_at')]);
        }

        $allClients = Client::orderBy('first_name')->orderBy('apellido_paterno')->orderBy('apellido_materno')->get(['id', 'first_name', 'apellido_paterno', 'apellido_materno', 'email']);

        return view('pets.show', compact('client', 'pet', 'isRootView', 'returnViewMode', 'allClients', 'clinicalModuleEnabled'));
    }

    public function destroy(Request $request, Pet $pet)
    {
        $activeBookings = $pet->spaBookings()
            ->whereIn('status', ['scheduled', 'work_order'])
            ->exists();

        if ($activeBookings) {
            return redirect()->back()
                ->with('error', 'No se puede eliminar la mascota porque tiene citas activas. Cancélalas primero.');
        }

        $pet->delete();

        $viewMode = $request->query('view') === 'table' ? 'table' : 'blocks';

        return redirect()
            ->route('pets.index', ['view' => $viewMode])
            ->with('success', 'Mascota eliminada correctamente.');
    }

    public function updateProfilePhoto(Request $request, Pet $pet, ?string $redirectRoute = null)
    {
        try {
            Log::info('Attempting to update profile photo', [
                'pet_id' => $pet->id,
                'has_file' => $request->hasFile('photo'),
                'file_size' => $request->hasFile('photo') ? $request->file('photo')->getSize() : 0,
            ]);

            $request->validate([
                'photo' => 'required|image|max:15360',
            ]);

            $imageManager = app(PetPhotoImageManager::class);
            $newPhotoPath = $imageManager->store($request->file('photo'));

            // Update the pet profile column
            $pet->update(['profile_photo_path' => $newPhotoPath]);

            // Also create a bitacora entry for traceability
            $pet->photos()->create([
                'photo_url' => $newPhotoPath,
                'photo_type' => 'perfil',
                'taken_at' => $imageManager->extractTakenAt($request->file('photo')),
                'is_primary' => true,
            ]);

            // Mark other photos as not primary
            $pet->photos()->where('photo_url', '!=', $newPhotoPath)->update(['is_primary' => false]);

            if ($redirectRoute) {
                return redirect($redirectRoute)->with('success', 'Foto de perfil actualizada correctamente.');
            }

            return redirect()->route('pets.show', $pet)->with('success', 'Foto de perfil actualizada correctamente.');
        } catch (\Exception $e) {
            Log::error('FAILED to update profile photo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['photo' => 'Error interno al procesar la imagen: '.$e->getMessage()]);
        }
    }

    public function updateProfilePhotoFromClient(Request $request, Client $client, Pet $pet)
    {
        return $this->updateProfilePhoto($request, $pet, route('clients.pets.show', [$client, $pet]));
    }

    public function destroyFromClient(Client $client, Pet $pet)
    {
        $pet->delete();

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Mascota eliminada correctamente.');
    }

    private function validatedPetData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'species' => ['nullable', 'string', 'max:255'],
            'breed' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'death_date' => ['nullable', 'date', 'after_or_equal:birth_date'],
            'microchip_code' => ['nullable', 'string', 'max:255'],
            'tattoo_code' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'in:male,female,unknown'],
            'coat_color' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'in:mini,small,medium,large,giant'],
            'is_sterilized' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
