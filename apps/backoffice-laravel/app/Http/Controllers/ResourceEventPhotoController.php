<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\ResourceEvent;
use App\Models\ResourceEventPhoto;
use App\Models\ResourceEventUpdate;
use App\Support\ResourceEventPhotoImageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResourceEventPhotoController extends Controller
{
    public function __construct(private readonly ResourceEventPhotoImageManager $imageManager)
    {
    }

    public function store(Request $request, Resource $resource, ResourceEvent $event): RedirectResponse
    {
        $this->ensureEventBelongsToResource($resource, $event);

        $data = $this->validatedData($request, $event);
        $newPhotoPath = null;

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('photo'));
            $data['photo_url'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($event, $data): void {
                if ($data['is_primary']) {
                    $event->photos()->update(['is_primary' => false]);
                }

                $event->photos()->create($data);
            });
        } catch (Throwable $exception) {
            $this->imageManager->deleteFiles($newPhotoPath);

            throw $exception;
        }

        return redirect()
            ->route('resources.events.show', [$resource, $event])
            ->with('success', 'Foto del evento creada.')
            ->withFragment('event-photos');
    }

    public function update(Request $request, Resource $resource, ResourceEvent $event, ResourceEventPhoto $photo): RedirectResponse
    {
        $this->ensureEventBelongsToResource($resource, $event);
        $this->ensurePhotoBelongsToEvent($event, $photo);

        $data = $this->validatedData($request, $event);
        $oldPhotoPath = $photo->storesExternalUrl() ? null : $photo->photo_url;
        $newPhotoPath = null;

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('photo'));
            $data['photo_url'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($event, $photo, $data): void {
                if ($data['is_primary']) {
                    $event->photos()->whereKeyNot($photo->id)->update(['is_primary' => false]);
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
            ->route('resources.events.show', [$resource, $event])
            ->with('success', 'Foto del evento actualizada.')
            ->withFragment('event-photos');
    }

    public function destroy(Resource $resource, ResourceEvent $event, ResourceEventPhoto $photo): RedirectResponse
    {
        $this->ensureEventBelongsToResource($resource, $event);
        $this->ensurePhotoBelongsToEvent($event, $photo);

        $storedPhotoPath = $photo->storesExternalUrl() ? null : $photo->photo_url;
        $photo->delete();
        $this->imageManager->deleteFiles($storedPhotoPath);

        return redirect()
            ->route('resources.events.show', [$resource, $event])
            ->with('success', 'Foto del evento eliminada.')
            ->withFragment('event-photos');
    }

    private function validatedData(Request $request, ResourceEvent $event): array
    {
        $validated = $request->validate([
            'resource_event_update_id' => 'nullable|integer|exists:resource_event_updates,id',
            'photo_type' => 'required|string|max:255',
            'taken_at' => 'nullable|date',
            'description' => 'nullable|string',
            'is_primary' => 'nullable|boolean',
            'photo' => [$request->isMethod('post') ? 'required' : 'nullable', 'file', 'image', 'max:15360'],
        ]);

        if (!empty($validated['resource_event_update_id'])) {
            $update = ResourceEventUpdate::query()->find($validated['resource_event_update_id']);
            abort_unless($update && $update->resource_event_id === $event->id, 422);
        }

        $data = [
            'resource_event_update_id' => $validated['resource_event_update_id'] ?? null,
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

    private function ensureEventBelongsToResource(Resource $resource, ResourceEvent $event): void
    {
        abort_unless($event->resource_id === $resource->id, 404);
    }

    private function ensurePhotoBelongsToEvent(ResourceEvent $event, ResourceEventPhoto $photo): void
    {
        abort_unless($photo->resource_event_id === $event->id, 404);
    }
}