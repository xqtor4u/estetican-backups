{{--
    Barra de pestañas de la ficha de mascota (Resumen/Agenda/Servicios/Historial/Cobros) — todas
    del dominio de estética/operación diaria. Veterinaria NO vive aquí: es un módulo separado
    (`clinical.*`, con su propia pantalla de búsqueda de mascotas en `clinical.index` y su propio
    expediente en `clinical.pets.show`) que solo comparte datos maestros (Client/Pet), nunca
    pantallas — a propósito, para no acoplar visualmente los dos flujos.
--}}
@props(['pet', 'activeTab' => 'resumen'])

@php
    $localTabs = [
        'resumen'   => 'Resumen',
        'agenda'    => 'Agenda',
        'servicios' => 'Servicios',
        'historial' => 'Historial',
        'cobros'    => 'Cobros',
    ];
@endphp

<ul class="nav nav-tabs mb-4" role="tablist">
    @foreach($localTabs as $slug => $label)
        <li class="nav-item" role="presentation">
            <button
                class="nav-link {{ $activeTab === $slug ? 'active' : '' }}"
                id="pet-tab-{{ $slug }}-btn"
                data-bs-toggle="tab"
                data-bs-target="#pet-tab-{{ $slug }}"
                type="button"
                role="tab"
                aria-controls="pet-tab-{{ $slug }}"
                aria-selected="{{ $activeTab === $slug ? 'true' : 'false' }}"
            >{{ $label }}</button>
        </li>
    @endforeach
</ul>
