<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\ResourcePhoto;
use App\Support\ResourcePhotoImageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResourcePhotoController extends Controller
{
    public function __construct(private readonly ResourcePhotoImageManager $imageManager)
    {
    }

    public function store(Request $request, Resource $resource): RedirectResponse
    {
        $data = $this->validatedData($request);
        $newPhotoPath = null;

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('photo'));
            $data['photo_url'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($resource, $data): void {
                if ($data['is_primary']) {
                    $resource->photos()->update(['is_primary' => false]);
                }

                $resource->photos()->create($data);
            });
        } catch (Throwable $exception) {
            $this->imageManager->deleteFiles($newPhotoPath);

            throw $exception;
        }

        return redirect()
            ->route('resources.show', $resource)
            ->with('success', 'Foto del recurso creada.')
            ->withFragment('resource-photos');
    }

    public function update(Request $request, Resource $resource, ResourcePhoto $photo): RedirectResponse
    {
        $this->ensurePhotoBelongsToResource($resource, $photo);

        $data = $this->validatedData($request);
        $oldPhotoPath = $photo->storesExternalUrl() ? null : $photo->photo_url;
        $newPhotoPath = null;

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('photo'));
            $data['photo_url'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($resource, $photo, $data): void {
                if ($data['is_primary']) {
                    $resource->photos()->whereKeyNot($photo->id)->update(['is_primary' => false]);
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
            ->route('resources.show', $resource)
            ->with('success', 'Foto del recurso actualizada.')
            ->withFragment('resource-photos');
    }

    public function destroy(Resource $resource, ResourcePhoto $photo): RedirectResponse
    {
        $this->ensurePhotoBelongsToResource($resource, $photo);

        $storedPhotoPath = $photo->storesExternalUrl() ? null : $photo->photo_url;

        $photo->delete();
        $this->imageManager->deleteFiles($storedPhotoPath);

        return redirect()
            ->route('resources.show', $resource)
            ->with('success', 'Foto del recurso eliminada.')
            ->withFragment('resource-photos');
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
            'photo_type' => trim($validated['photo_type']),
            'taken_at' => $validated['taken_at'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
        ];

        if (!$data['taken_at'] && $request->hasFile('photo')) {
            $data['taken_at'] = $this->imageManager->extractTakenAt($request->file('photo'));
        }

        return $data;
    }

    private function ensurePhotoBelongsToResource(Resource $resource, ResourcePhoto $photo): void
    {
        abort_unless($photo->resource_id === $resource->id, 404);
    }
}