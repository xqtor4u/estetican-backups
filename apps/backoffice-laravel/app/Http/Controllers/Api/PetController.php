<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Pet;
use App\Support\PetPhotoImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PetController extends Controller
{
    public function __construct(private PetPhotoImageManager $imageManager)
    {
    }

    /** Guarda y comprime la foto, actualiza profile_photo_path y deja registro en la galería (mismo patrón que el backoffice web) */
    private function storeProfilePhoto(Pet $pet, $file): string
    {
        $newPhotoPath = $this->imageManager->store($file);

        $pet->update(['profile_photo_path' => $newPhotoPath]);

        $pet->photos()->create([
            'photo_url' => $newPhotoPath,
            'photo_type' => 'perfil',
            'taken_at' => $this->imageManager->extractTakenAt($file),
            'is_primary' => true,
        ]);
        $pet->photos()->where('photo_url', '!=', $newPhotoPath)->update(['is_primary' => false]);

        return Storage::disk('public')->url($newPhotoPath);
    }

    public function show(Pet $pet)
    {
        $pet->load('client', 'medicalAlerts', 'spaBookings');

        return [
            'id'           => $pet->id,
            'name'         => $pet->name,
            'species'      => $pet->species,
            'breed'        => $pet->breed,
            'sex'          => $pet->sex,
            'size'         => $pet->size,
            'coat_color'   => $pet->coat_color,
            'birth_date'   => $pet->birth_date?->format('d/m/Y'),
            'age'          => $pet->age_description,
            'is_sterilized'        => $pet->is_sterilized,
            'flagged_for_deletion' => $pet->flagged_for_deletion,
            'microchip'    => $pet->microchip_code,
            'tattoo'       => $pet->tattoo_code,
            'notes'        => $pet->notes,
            'photo'        => $pet->profile_photo_path ? Storage::disk('public')->url($pet->profile_photo_path) : null,
            'owner'        => $pet->client ? [
                'id'    => $pet->client->id,
                'name'  => $pet->client->full_name,
                'phone' => $pet->client->phones()->first()?->number ?? null,
            ] : null,
            'medical_alerts' => $pet->medicalAlerts->map(fn ($a) => [
                'id'          => $a->id,
                'description' => $a->description,
                'severity'    => $a->severity ?? 'warning',
            ]),
            'upcoming_bookings' => $pet->spaBookings()
                ->whereIn('status', ['scheduled', 'work_order'])
                ->where('scheduled_at', '>=', now())
                ->oldest('scheduled_at')
                ->limit(5)
                ->get()
                ->map(fn ($b) => [
                    'id'     => $b->id,
                    'date'   => $b->scheduled_at->format('d M Y'),
                    'time'   => $b->scheduled_at->format('H:i'),
                    'status' => $b->status,
                ]),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'      => 'required|exists:clients,id',
            'name'           => 'required|string|max:255',
            'species'        => 'nullable|string|max:255',
            'breed'          => 'nullable|string|max:255',
            'sex'            => 'nullable|in:male,female',
            'size'           => 'nullable|in:small,medium,large,giant',
            'coat_color'     => 'nullable|string|max:255',
            'birth_date'     => 'nullable|date',
            'microchip_code' => 'nullable|string|max:255',
            'tattoo_code'    => 'nullable|string|max:255',
            'is_sterilized'  => 'boolean',
            'notes'          => 'nullable|string',
        ]);

        $pet = Pet::create($data);

        // Foto de perfil (si se envió)
        if ($request->hasFile('photo')) {
            $this->storeProfilePhoto($pet, $request->file('photo'));
        }

        return response()->json(['id' => $pet->id], 201);
    }

    public function updatePhoto(Request $request, Pet $pet)
    {
        $request->validate(['photo' => 'required|image|max:15360']);

        $photoUrl = $this->storeProfilePhoto($pet, $request->file('photo'));

        return response()->json(['photo' => $photoUrl]);
    }

    public function deletePhoto(Pet $pet)
    {
        $this->imageManager->deleteFiles($pet->profile_photo_path);
        $pet->update(['profile_photo_path' => null]);
        $pet->photos()->where('photo_type', 'perfil')->update(['is_primary' => false]);

        return response()->json(['photo' => null]);
    }

    public function update(Request $request, Pet $pet)
    {
        $data = $request->validate([
            'name'                 => 'sometimes|required|string|max:255',
            'species'              => 'sometimes|nullable|string|max:255',
            'breed'                => 'sometimes|nullable|string|max:255',
            'sex'                  => 'sometimes|nullable|in:male,female',
            'size'                 => 'sometimes|nullable|in:small,medium,large,giant',
            'coat_color'           => 'sometimes|nullable|string|max:255',
            'birth_date'           => 'sometimes|nullable|date',
            'death_date'           => 'sometimes|nullable|date',
            'microchip_code'       => 'sometimes|nullable|string|max:255',
            'tattoo_code'          => 'sometimes|nullable|string|max:255',
            'is_sterilized'        => 'sometimes|boolean',
            'client_id'            => 'sometimes|exists:clients,id',
            'flagged_for_deletion' => 'sometimes|boolean',
            'notes'                => 'sometimes|nullable|string',
        ]);

        $pet->update($data);

        return response()->json(['ok' => true]);
    }

    public function index(Request $request)
    {
        $query = Pet::visible()
            ->with('client')
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('breed', 'like', "%{$search}%")
                  ->orWhereHas('client', fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                                                       ->orWhere('apellido_paterno', 'like', "%{$search}%")
                                                       ->orWhere('apellido_materno', 'like', "%{$search}%"));
            });
        }

        return $query->get()->map(fn (Pet $pet) => [
            'id'         => $pet->id,
            'name'       => $pet->name,
            'breed'      => $pet->breed,
            'owner'      => $pet->client?->full_name,
            'photo'      => $pet->profile_photo_path
                                ? Storage::disk('public')->url($pet->profile_photo_path)
                                : null,
            'next_visit' => optional(
                                $pet->spaBookings()
                                    ->whereIn('status', ['scheduled', 'work_order'])
                                    ->where('scheduled_at', '>=', now())
                                    ->oldest('scheduled_at')
                                    ->first()
                            )->scheduled_at?->format('d M'),
        ]);
    }
}
