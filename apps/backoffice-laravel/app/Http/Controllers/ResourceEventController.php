<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Resource;
use App\Models\ResourceEvent;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResourceEventController extends Controller
{
    public function store(Request $request, Resource $resource): RedirectResponse
    {
        $data = $this->validatedData($request);
        $event = $resource->events()->create($data);

        return redirect()
            ->route('resources.events.show', [$resource, $event])
            ->with('success', 'Evento operativo creado.');
    }

    public function show(Resource $resource, ResourceEvent $event): View
    {
        $this->ensureEventBelongsToResource($resource, $event);

        $event->load([
            'resource.branch:id,name,code',
            'client:id,first_name,last_name',
            'pet:id,name,client_id',
            'service:id,name,code',
            'detectedBy:id,name',
            'responsibleUser:id,name',
            'closedBy:id,name',
            'updates' => fn ($query) => $query
                ->with('createdBy:id,name')
                ->orderByDesc('created_at'),
            'photos' => fn ($query) => $query
                ->with('eventUpdate:id,resource_event_id,update_type,created_at')
                ->orderByDesc('is_primary')
                ->orderByDesc('taken_at')
                ->orderByDesc('created_at'),
        ]);

        $eventFormUsers = User::query()->orderBy('name')->get(['id', 'name']);
        $updateOptions = $event->updates()->orderByDesc('created_at')->get(['id', 'resource_event_id', 'update_type', 'created_at']);

        return view('resources.events.show', compact('resource', 'event', 'eventFormUsers', 'updateOptions'));
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'event_type' => 'required|string|max:80',
            'event_status' => 'required|string|max:60',
            'severity' => 'required|string|max:40',
            'title' => 'required|string|max:160',
            'description' => 'nullable|string',
            'occurred_at' => 'nullable|date',
            'detected_at' => 'nullable|date',
            'detected_by_user_id' => 'nullable|integer|exists:users,id',
            'responsible_user_id' => 'nullable|integer|exists:users,id',
            'client_id' => 'nullable|integer|exists:clients,id',
            'pet_id' => 'nullable|integer|exists:pets,id',
            'service_id' => 'nullable|integer|exists:services,id',
        ]);

        if (!empty($validated['pet_id']) && !empty($validated['client_id'])) {
            $pet = Pet::query()->find($validated['pet_id']);

            abort_unless($pet && $pet->client_id === (int) $validated['client_id'], 422);
        }

        if (!empty($validated['pet_id']) && empty($validated['client_id'])) {
            $pet = Pet::query()->find($validated['pet_id']);
            $validated['client_id'] = $pet?->client_id;
        }

        return [
            'event_type' => trim($validated['event_type']),
            'event_status' => trim($validated['event_status']),
            'severity' => trim($validated['severity']),
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
            'occurred_at' => $validated['occurred_at'] ?? null,
            'detected_at' => $validated['detected_at'] ?? now(),
            'detected_by_user_id' => $validated['detected_by_user_id'] ?? auth()->id(),
            'responsible_user_id' => $validated['responsible_user_id'] ?? null,
            'client_id' => $validated['client_id'] ?? null,
            'pet_id' => $validated['pet_id'] ?? null,
            'service_id' => $validated['service_id'] ?? null,
        ];
    }

    private function ensureEventBelongsToResource(Resource $resource, ResourceEvent $event): void
    {
        abort_unless($event->resource_id === $resource->id, 404);
    }
}