<div class="row g-3">
    <div class="col-md-6">
        <label for="pet_id" class="form-label">Mascota</label>
        <select id="pet_id" name="pet_id" class="form-select" required>
            <option value="">Seleccionar mascota</option>
            @foreach($pets as $pet)
                <option value="{{ $pet->id }}" @selected((string) old('pet_id', $hotelReservation->pet_id ?? null) === (string) $pet->id)>
                    {{ $pet->name }}@if($pet->client) · {{ trim($pet->client->first_name . ' ' . $pet->client->last_name) }}@endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label for="resource_id" class="form-label">Jaula / recurso de hospedaje</label>
        <select id="resource_id" name="resource_id" class="form-select">
            <option value="">Sin asignar por ahora</option>
            @foreach($resources as $resource)
                <option value="{{ $resource->id }}" @selected((string) old('resource_id', $assignedResourceId ?? null) === (string) $resource->id)>
                    {{ $resource->code }} · {{ $resource->name }} · {{ $resource->branch?->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">La jaula queda bloqueada durante todo el rango reservado. La limpieza posterior del hotel se resolverá desde la ocupación real (`stay`).</div>
    </div>

    <div class="col-md-6">
        <label for="start_at" class="form-label">Inicio de hospedaje</label>
        <input id="start_at" type="datetime-local" name="start_at" value="{{ old('start_at', optional($hotelReservation->start_at ?? null)->format('Y-m-d\TH:i')) }}" class="form-control" required>
    </div>

    <div class="col-md-6">
        <label for="end_at" class="form-label">Fin planeado</label>
        <input id="end_at" type="datetime-local" name="end_at" value="{{ old('end_at', optional($hotelReservation->end_at ?? null)->format('Y-m-d\TH:i')) }}" class="form-control" required>
    </div>
</div>