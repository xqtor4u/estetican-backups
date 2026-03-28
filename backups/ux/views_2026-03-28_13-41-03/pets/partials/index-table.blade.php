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
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($pets as $pet)
            <tr class="{{ $pet->death_date ? 'table-secondary' : '' }}">
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
                    <span class="badge {{ $pet->death_date ? 'text-bg-secondary' : 'text-bg-success' }}">
                        {{ $pet->death_date ? 'Fallecida' : 'Activa' }}
                    </span>
                </td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        <a href="{{ route('pets.show', ['pet' => $pet, 'view' => $viewMode]) }}" class="btn btn-sm btn-outline-primary">Detalle</a>
                        <a href="{{ route('clients.show', $pet->client) }}" class="btn btn-sm btn-outline-secondary">Cliente</a>
                        <a href="{{ route('pets.bookings.create', ['pet' => $pet, 'return_view_mode' => $viewMode]) }}" class="btn btn-sm btn-outline-dark catalog-action-upcoming">
                            Programar
                        </a>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center py-4 text-body-secondary">Aún no hay mascotas registradas.</td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>