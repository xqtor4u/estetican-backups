<x-list-table :paginator="$clients">
    <thead>
        <tr>
            <th>
                <x-sortable-header-link route="clients.index" column="first_name" label="Nombre" :sort="$sort" :direction="$direction" :query="['view' => $viewMode]" />
            </th>
            <th>
                <x-sortable-header-link route="clients.index" column="last_name" label="Apellido" :sort="$sort" :direction="$direction" :query="['view' => $viewMode]" />
            </th>
            <th>
                <x-sortable-header-link route="clients.index" column="email" label="Contacto" :sort="$sort" :direction="$direction" :query="['view' => $viewMode]" />
            </th>
            <th>
                <x-sortable-header-link route="clients.index" column="pets" label="Mascotas vivas" :sort="$sort" :direction="$direction" :query="['view' => $viewMode]" />
            </th>
            <th class="text-end">Acciones</th>
        </tr>
    </thead>
    <tbody>
        @forelse($clients as $client)
            @php
                $primaryPhone = $client->phones->first();
                $livePets = $client->pets;
            @endphp
            <tr>
                <td>
                    <div class="fw-semibold">{{ $client->first_name }}</div>
                </td>
                <td>
                    <div class="fw-semibold">{{ $client->last_name }}</div>
                </td>
                <td>
                    @if($primaryPhone)
                        <div class="fw-semibold">{{ $client->email }}</div>
                        <div class="text-body-secondary small">{{ $primaryPhone->number }} · {{ match($primaryPhone->type) { 'mobile' => 'Móvil', 'fixed' => 'Fijo', default => ucfirst($primaryPhone->type) } }}</div>
                    @else
                        <div class="fw-semibold">{{ $client->email }}</div>
                        <span class="text-body-secondary small">Sin teléfono</span>
                    @endif
                </td>
                <td>
                    <div class="fw-semibold">{{ $client->live_pets_count }}</div>
                    @if($livePets->isNotEmpty())
                        <div class="text-body-secondary small">{{ $livePets->pluck('name')->take(3)->implode(' · ') }}</div>
                    @else
                        <div class="text-body-secondary small">Sin mascotas vivas</div>
                    @endif
                </td>
                <td class="text-end">
                    <div class="catalog-actions-cluster justify-content-end">
                        @if($petCreationMode ?? false)
                            <a href="{{ route('clients.edit', ['client' => $client, 'open_pet_modal' => 1]) }}" class="btn btn-sm btn-outline-primary">Agregar mascota aquí</a>
                        @else
                            <a href="{{ route('clients.show', $client) }}" class="btn btn-sm btn-outline-info">Cliente</a>
                            @if($livePets->isNotEmpty())
                                <a href="{{ route('clients.pets.show', [$client, $livePets->first()]) }}" class="btn btn-sm btn-outline-primary">Mascota</a>
                            @endif
                            <a href="{{ route('clients.edit', $client) }}" class="btn btn-sm btn-outline-warning">Editar</a>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="py-5">
                    <x-empty-state 
                        icon="bi-person-badge-fill"
                        title="No hay clientes registrados"
                        subtitle="No se encontraron clientes en el sistema bajo los filtros actuales."
                        action-label="Crear mi primer cliente"
                        :action-route="route('clients.create')"
                    />
                </td>
            </tr>
        @endforelse
    </tbody>
</x-list-table>