@props([
    'route',
    'column',
    'label',
    'sort' => null,
    'direction' => 'asc',
    'query' => [],
])

@php
    $isCurrentSort = $sort === $column;
    $nextDirection = $isCurrentSort && $direction === 'asc' ? 'desc' : 'asc';
    $indicator = $isCurrentSort ? ($direction === 'asc' ? '↑' : '↓') : '';
    $statusLabel = $isCurrentSort
        ? ($direction === 'asc' ? 'Orden actual ascendente' : 'Orden actual descendente')
        : 'Sin orden activo';
@endphp

<a href="{{ route($route, array_merge(request()->query(), $query, ['sort' => $column, 'direction' => $nextDirection])) }}" class="catalog-sort-link link-dark text-decoration-none">
    <span>{{ $label }}</span>
    @if($indicator !== '')
        <span class="catalog-sort-link__indicator" aria-hidden="true">{{ $indicator }}</span>
        <span class="visually-hidden">{{ $statusLabel }}</span>
    @endif
</a>