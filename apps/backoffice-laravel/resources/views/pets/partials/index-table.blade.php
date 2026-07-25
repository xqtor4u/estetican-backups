<x-list-table :paginator="$pets">
    <thead>
        <tr>
            <th>
                <x-sortable-header-link route="pets.index" column="name" label="Mascota" :sort="$sort" :direction="$direction" :query="['view' => $viewMode]" />
            </th>
            <th>
                <x-sortable-header-link route="pets.index" column="client" label="Cliente" :sort="$sort" :direction="$direction" :query="['view' => $viewMode]" />
            </th>
            <th>
                <x-sortable-header-link route="pets.index" column="species" label="Perfil" :sort="$sort" :direction="$direction" :query="['view' => $viewMode]" />
            </th>
            <th>
                <x-sortable-header-link route="pets.index" column="status" label="Estado" :sort="$sort" :direction="$direction" :query="['view' => $viewMode]" />
            </th>
            <th>Última Visita</th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($pets as $pet)
            <tr class="{{ ($pet->death_date || !$pet->is_active) ? 'table-secondary' : '' }}">
                <td>
                    <div class="fw-semibold">{{ $pet->name }}</div>
                    <div class="text-body-secondary small">
                        @if($pet->species_label)
                            {{ $pet->species_label }}
                        @endif
                        @if($pet->species_label && $pet->breed)
                            ·
                        @endif
                        @if($pet->breed)
                            {{ $pet->breed }}
                        @endif
                    </div>
                </td>
                <td>
                    <div class="fw-semibold">{{ $pet->client->first_name }} {{ $pet->client->last_name }}</div>
                    <div class="text-body-secondary small">{{ $pet->client->email }}</div>
                </td>
                <td>
                    <div>{{ $pet->age_description ?: 'Edad sin dato' }}</div>
                    <div class="text-body-secondary small">Chip: {{ $pet->microchip_code ?: 'Sin dato' }}</div>
                </td>
                <td>
                    <span class="badge {{ $pet->death_date ? 'text-bg-secondary' : ($pet->is_active ? 'text-bg-success' : 'text-bg-warning') }}">
                        {{ $pet->death_date ? 'Fallecida' : ($pet->is_active ? 'Activa' : 'Inactiva') }}
                    </span>
                </td>
                <td>
                    @php
                        $lastVisit = $pet->last_spa_at ? \Carbon\Carbon::parse($pet->last_spa_at) : null;
                        if ($pet->last_hotel_at) {
                            $hotelDate = \Carbon\Carbon::parse($pet->last_hotel_at);
                            $lastVisit = $lastVisit ? $lastVisit->max($hotelDate) : $hotelDate;
                        }
                    @endphp
                    @if($lastVisit)
                        <div class="fw-semibold text-primary">{{ $lastVisit->format('d/m/Y') }}</div>
                        <div class="text-body-secondary small">{{ $lastVisit->diffForHumans() }}</div>
                    @else
                        <span class="text-body-secondary small">Sin visitas</span>
                    @endif
                </td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        <a href="{{ route('pets.show', ['pet' => $pet, 'view' => $viewMode]) }}" class="btn btn-sm btn-outline-primary" title="Ver ficha técnica (PETSHO)">Detalle</a>
                        <a href="{{ route('pets.show', ['pet' => $pet, 'view' => $viewMode]) }}#core-profile" class="btn btn-sm btn-outline-secondary" title="Editar datos base (PETEDI)">Editar</a>
                        <a href="{{ route('pets.bookings.create', ['pet' => $pet, 'return_view_mode' => $viewMode]) }}" class="btn btn-sm btn-outline-dark catalog-action-upcoming">
                            Programar
                        </a>
                        <form action="{{ route('pets.destroy', ['pet' => $pet, 'view' => $viewMode]) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="No elimina, conserva historial" data-confirm="¿Marcar a {{ $pet->name }} como inactiva? No se elimina — su historial (citas, pagos, expediente) se conserva completo y puede reactivarse después desde su ficha.">
                                <i class="bi bi-trash"></i> Inactivar
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="py-5">
                    <x-empty-state 
                        icon="bi-qr-code-scan"
                        title="No hay mascotas registradas"
                        subtitle="No se encontraron mascotas en el catálogo bajo los filtros aplicados."
                        action-label="Ir a clientes para registrar"
                        :action-route="route('clients.index')"
                    />
                </td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>