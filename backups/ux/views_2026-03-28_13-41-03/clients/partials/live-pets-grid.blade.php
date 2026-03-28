<div class="catalog-block-grid catalog-block-grid--compact">
    @foreach($pets as $pet)
        <div class="card h-100 catalog-block-card">
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
                    <h6 class="card-title mb-1">{{ $pet->name }}</h6>
                    <p class="text-muted small mb-2">
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
                        @if($pet->age_description)
                            <div>{{ $pet->age_description }}</div>
                        @endif
                        @if($pet->microchip_code)
                            <div>Chip: {{ $pet->microchip_code }}</div>
                        @endif
                    </div>
                    <a href="{{ route('clients.pets.show', [$client, $pet]) }}" class="btn btn-sm btn-outline-primary mt-auto align-self-start">Gestionar</a>
                </div>
            </div>
        </div>
    @endforeach
</div>