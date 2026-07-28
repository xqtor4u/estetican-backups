<div class="catalog-block-grid">
    @forelse($pets as $pet)
        <div class="card h-100 catalog-block-card {{ ($pet->death_date || !$pet->is_active) ? 'border-secondary' : '' }}">
            <div class="card-body d-flex gap-3 align-items-start">
                <div class="bg-light border rounded overflow-hidden flex-shrink-0 app-media-thumb">
                    @if($pet->catalog_thumbnail_url)
                        <img src="{{ parse_url($pet->catalog_thumbnail_url, PHP_URL_PATH) }}" alt="Foto de {{ $pet->name }}" width="160" height="120" class="w-100 h-100 app-media-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="align-items-center justify-content-center text-primary fw-bold w-100 h-100 bg-primary-subtle" style="display: none; font-size: 2rem;">
                            {{ Str::of($pet->name)->substr(0, 1)->upper() }}
                        </div>
                    @else
                        <div class="d-flex align-items-center justify-content-center text-muted small w-100 h-100">
                            Sin foto
                        </div>
                    @endif
                </div>
                <div class="d-flex flex-column min-w-0 flex-grow-1">
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-1">
                        <h6 class="card-title mb-0">{{ $pet->name }}</h6>
                        <span class="badge {{ $pet->death_date ? 'text-bg-secondary' : ($pet->is_active ? 'text-bg-success' : 'text-bg-warning') }}">
                            {{ $pet->death_date ? 'Fallecida' : ($pet->is_active ? 'Activa' : 'Inactiva') }}
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
                        @php
                            $lastVisit = $pet->last_spa_at ? \Carbon\Carbon::parse($pet->last_spa_at) : null;
                            if ($pet->last_hotel_at) {
                                $hotelDate = \Carbon\Carbon::parse($pet->last_hotel_at);
                                $lastVisit = $lastVisit ? $lastVisit->max($hotelDate) : $hotelDate;
                            }
                        @endphp
                        <div class="mb-1">
                            @if($lastVisit)
                                <span class="text-primary fw-semibold">Última visita: {{ $lastVisit->format('d/m/Y') }}</span>
                            @else
                                <span class="text-body-secondary">Sin visitas registradas</span>
                            @endif
                        </div>
                        <div>{{ $pet->age_description ?: 'Edad sin dato' }}</div>
                        <div>Chip: {{ $pet->microchip_code ?: 'Sin dato' }}</div>
                    </div>
                    <div class="catalog-actions-cluster mt-auto">
                        <a href="{{ route('pets.show', ['pet' => $pet, 'view' => $viewMode]) }}" class="btn btn-sm btn-outline-primary" title="Ver ficha técnica (PETSHO)">Detalle</a>
                        <a href="{{ route('pets.show', ['pet' => $pet, 'view' => $viewMode]) }}#core-profile" class="btn btn-sm btn-outline-secondary" title="Editar datos base (PETEDI)">Editar</a>
                        <a href="{{ route('pets.bookings.create', ['pet' => $pet, 'return_view_mode' => $viewMode]) }}" class="btn btn-sm btn-outline-dark catalog-action-upcoming">Programar</a>
                        @if($pet->is_active)
                            <form action="{{ route('pets.destroy', ['pet' => $pet, 'view' => $viewMode]) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="No elimina, conserva historial" data-confirm="¿Marcar a {{ $pet->name }} como inactiva? No se elimina — su historial (citas, pagos, expediente) se conserva completo y puede reactivarse después desde su ficha.">
                                    <i class="bi bi-trash"></i> Inactivar
                                </button>
                            </form>
                        @endif
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