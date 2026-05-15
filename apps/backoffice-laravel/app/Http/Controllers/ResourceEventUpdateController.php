<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\ResourceEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResourceEventUpdateController extends Controller
{
    public function store(Request $request, Resource $resource, ResourceEvent $event): RedirectResponse
    {
        $this->ensureEventBelongsToResource($resource, $event);

        $validated = $request->validate([
            'update_type' => 'required|string|max:80',
            'to_status' => 'nullable|string|max:60',
            'notes' => 'nullable|string',
        ]);

        $toStatus = isset($validated['to_status']) && trim((string) $validated['to_status']) !== ''
            ? trim((string) $validated['to_status'])
            : null;
        $fromStatus = $toStatus && $toStatus !== $event->event_status
            ? $event->event_status
            : null;

        $event->updates()->create([
            'update_type' => trim($validated['update_type']),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => auth()->id(),
        ]);

        if ($toStatus && $toStatus !== $event->event_status) {
            $payload = ['event_status' => $toStatus];

            if (in_array($toStatus, ['resolved', 'closed'], true)) {
                $payload['resolved_at'] = now();
                $payload['closed_by_user_id'] = auth()->id();
            }

            $event->update($payload);
        }

        return redirect()
            ->route('resources.events.show', [$resource, $event])
            ->with('success', 'Seguimiento registrado.')
            ->withFragment('event-updates');
    }

    private function ensureEventBelongsToResource(Resource $resource, ResourceEvent $event): void
    {
        abort_unless($event->resource_id === $resource->id, 404);
    }
}