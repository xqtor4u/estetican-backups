@props([
    'icon' => 'bi-search',
    'title' => 'No se encontraron resultados',
    'subtitle' => 'Intenta ajustar tus filtros o buscar términos menos específicos.',
    'actionLabel' => null,
    'actionRoute' => null,
])

<div class="empty-state-wrapper text-center py-5">
    <div class="empty-state-icon mb-4">
        <i class="bi {{ $icon }} display-1 text-body-tertiary opacity-50"></i>
    </div>
    <h3 class="h5 fw-bold mb-2">{{ $title }}</h3>
    <p class="text-muted mx-auto" style="max-width: 400px;">{{ $subtitle }}</p>
    
    @if($actionLabel && $actionRoute)
        <div class="mt-4">
            <a href="{{ $actionRoute }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                {{ $actionLabel }}
            </a>
        </div>
    @endif
</div>
