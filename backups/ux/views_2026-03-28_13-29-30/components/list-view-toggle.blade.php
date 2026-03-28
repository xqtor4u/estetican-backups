@props([
    'route',
    'viewMode' => 'blocks',
    'query' => [],
    'blockLabel' => 'Bloques',
    'tableLabel' => 'Tabla',
    'ariaLabel' => 'Cambiar presentación',
])

@php
    $baseQuery = array_merge(request()->query(), $query);
@endphp

<div class="catalog-view-toggle btn-group" role="group" aria-label="{{ $ariaLabel }}">
    <a href="{{ route($route, array_merge($baseQuery, ['view' => 'blocks'])) }}" class="catalog-view-toggle__button btn btn-outline-secondary {{ $viewMode === 'blocks' ? 'active' : '' }}">{{ $blockLabel }}</a>
    <a href="{{ route($route, array_merge($baseQuery, ['view' => 'table'])) }}" class="catalog-view-toggle__button btn btn-outline-secondary {{ $viewMode === 'table' ? 'active' : '' }}">{{ $tableLabel }}</a>
</div>