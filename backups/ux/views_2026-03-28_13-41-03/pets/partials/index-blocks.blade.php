<div class="catalog-block-grid">
    @forelse($pets as $pet)
        <div class="card h-100 catalog-block-card {{ $pet->death_date ? 'border-secondary' : '' }}">
            <div class="card-body d-flex gap-3 align-items-start">
                <div class="bg-light border rounded overflow-hidden flex-shrink-0 app-media-thumb">
                    @if($pet->catalog_thumbnail_url)
                        <img src="{{ $pet->catalog_thumbnail_url }}" alt="Foto de {{ $pet->name }}" width="160" height="120" class="w-100 h-100 app-media-cover">
                    @else
                        <div class="d-flex align-items-center justify-content-center text-muted small w-100 h-100">
                            Sin foto
                        </div>
                    @endif
                </div>
                <div class="d-flex flex-column min-w-0 flex-grow-1">
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-1">
                        <h6 class="card-title mb-0">{{ $pet->name }}</h6>
                        <span class="badge {{ $pet->death_date ? 'text-bg-secondary' : 'text-bg-success' }}">
                            {{ $pet->death_date ? 'Fallecida' : 'Activa' }}
                        </span>
                    </div>
                    <p class="text-muted small mb-2">
                        {{ $pet->client->first_name }} {{ $pet->client->last_name }}
                        @if($pet->species_label || $pet->breed)
                            ·
                        @endif
                        @if($pet->species_label)
                            {{ $pet->species_label }}
                        @endif
                        @if($pet->species_label && $pet->breed)
                            ·
                        @endif
                        @if($pet->breed)
                            {{ $pet->breed }}
                        @endif
                    </p>
                    <div class="small mb-3">
                        <div>{{ $pet->age_description ?: 'Edad sin dato' }}</div>
                        <div>Chip: {{ $pet->microchip_code ?: 'Sin dato' }}</div>
                        <div>{{ $pet->notes ? \Illuminate\Support\Str::limit($pet->notes, 80) : 'Sin notas registradas' }}</div>
                    </div>
                    <div class="catalog-actions-cluster mt-auto">
                        <a href="{{ route('pets.show', ['pet' => $pet, 'view' => $viewMode]) }}" class="btn btn-sm btn-outline-primary">Detalle</a>
                        <a href="{{ route('clients.show', $pet->client) }}" class="btn btn-sm btn-outline-secondary">Cliente</a>
                        <a href="{{ route('pets.bookings.create', ['pet' => $pet, 'return_view_mode' => $viewMode]) }}" class="btn btn-sm btn-outline-dark catalog-action-upcoming">Programar</a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card catalog-block-card">
            <div class="card-body text-center py-4 text-body-secondary">No hay mascotas que coincidan con los filtros actuales.</div>
        </div>
    @endforelse
</div>