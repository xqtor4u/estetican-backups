<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Pet;
use App\Models\PetPhoto;
use App\Support\PetPhotoImageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PetPhotoController extends Controller
{
    public function __construct(private readonly PetPhotoImageManager $imageManager)
    {
    }

    public function store(Request $request, Client $client, Pet $pet)
    {
        $data = $this->validatedData($request);
        $newPhotoPath = null;

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('photo'));
            $data['photo_url'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($pet, $data, $newPhotoPath) {
                if ($data['is_primary']) {
                    $pet->photos()->update(['is_primary' => false]);
                    if ($newPhotoPath) {
                        $pet->update(['profile_photo_path' => $newPhotoPath]);
                    }
                }

                $pet->photos()->create($data);
            });
        } catch (Throwable $exception) {
            $this->imageManager->deleteFiles($newPhotoPath);

            throw $exception;
        }

        return redirect()
            ->route('clients.pets.show', [$client, $pet])
            ->with('success', 'Foto creada.')
            ->withFragment('pet-photos');
    }

    public function update(Request $request, Client $client, Pet $pet, PetPhoto $photo)
    {
        $data = $this->validatedData($request);
        $oldPhotoPath = $photo->storesExternalUrl() ? null : $photo->photo_url;
        $newPhotoPath = null;

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('photo'));
            $data['photo_url'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($pet, $photo, $data, $newPhotoPath) {
                if ($data['is_primary']) {
                    $pet->photos()->whereKeyNot($photo->id)->update(['is_primary' => false]);
                    $pet->update(['profile_photo_path' => $newPhotoPath ?: $photo->photo_url]);
                }

                $photo->update($data);
            });
        } catch (Throwable $exception) {
            $this->imageManager->deleteFiles($newPhotoPath);

            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath) {
            $this->imageManager->deleteFiles($oldPhotoPath);
        }

        return redirect()
            ->route('clients.pets.show', [$client, $pet])
            ->with('success', 'Foto actualizada.')
            ->withFragment('pet-photos');
    }

    public function destroy(Client $client, Pet $pet, PetPhoto $photo)
    {
        $storedPhotoPath = $photo->storesExternalUrl() ? null : $photo->photo_url;

        $isPrimary = $photo->is_primary;
        $photo->delete();

        if ($isPrimary) {
            $nextPrimary = $pet->photos()->where('is_primary', true)->first();
            $pet->update(['profile_photo_path' => $nextPrimary?->photo_url]);
        }

        $this->imageManager->deleteFiles($storedPhotoPath);

        return redirect()
            ->route('clients.pets.show', [$client, $pet])
            ->with('success', 'Foto eliminada.')
            ->withFragment('pet-photos');
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'photo_type' => 'required|string|max:255',
            'taken_at' => 'nullable|date',
            'description' => 'nullable|string',
            'is_primary' => 'nullable|boolean',
            'photo' => [$request->isMethod('post') ? 'required' : 'nullable', 'file', 'image', 'max:15360'],
        ]);

        $data = [
            'photo_type' => $this->normalizePhotoType($validated['photo_type']),
            'taken_at' => $validated['taken_at'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
        ];

        if (!$data['taken_at'] && $request->hasFile('photo')) {
            $data['taken_at'] = $this->imageManager->extractTakenAt($request->file('photo'));
        }

        return $data;
    }

    private function normalizePhotoType(string $photoType): string
    {
        $normalized = trim($photoType);

        return strtolower($normalized) === 'profile' ? 'perfil' : $normalized;
    }
}