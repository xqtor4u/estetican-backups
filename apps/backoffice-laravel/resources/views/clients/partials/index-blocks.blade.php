@forelse($clients as $client)
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">{{ $client->first_name }} {{ $client->last_name }}</h5>
            <p class="card-text">Email: {{ $client->email }}</p>

            <h6>Direcciones:</h6>
            @if($client->addresses->count() > 0)
                <ul>
                    @foreach($client->addresses as $address)
                        <li>{{ match($address->type) { 'home' => 'Casa', 'work' => 'Trabajo', default => ucfirst($address->type) } }}: {{ $address->formatted_address }}</li>
                    @endforeach
                </ul>
            @else
                <p>No hay direcciones.</p>
            @endif

            <h6>Teléfonos:</h6>
            @if($client->phones->count() > 0)
                <ul>
                    @foreach($client->phones as $phone)
                        <li>{{ match($phone->type) { 'mobile' => 'Móvil', 'fixed' => 'Fijo', default => ucfirst($phone->type) } }}: {{ $phone->number }}</li>
                    @endforeach
                </ul>
            @else
                <p>No hay teléfonos.</p>
            @endif

            <h6>Mascotas vivas:</h6>
            @if($client->pets->count() > 0)
                @include('clients.partials.live-pets-grid', ['client' => $client, 'pets' => $client->pets])
            @else
                <p>No hay mascotas vivas.</p>
            @endif

            @if($petCreationMode)
                <a href="{{ route('clients.edit', ['client' => $client, 'open_pet_modal' => 1]) }}" class="btn btn-primary">Agregar mascota aquí</a>
            @else
                <a href="{{ route('clients.show', $client) }}" class="btn btn-info">Ver</a>
                <a href="{{ route('clients.edit', $client) }}" class="btn btn-warning">Editar</a>
                <form action="{{ route('clients.destroy', $client) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" data-confirm="¿Eliminar?">Eliminar</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="card">
        <div class="card-body text-center py-4 text-body-secondary">No hay clientes que coincidan con los filtros actuales.</div>
    </div>
@endforelse
