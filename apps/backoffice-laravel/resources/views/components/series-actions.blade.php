{{-- ZEUS-047: acciones de una cita virtual de serie recurrente. "Fijar" la vuelve cita real;
     "Descartar" avisa que no se atenderá y la quita de la agenda (queda en el historial de la serie).
     `chip` = filas de la Agenda unificada; `button` = detalle de la cita y pantalla de la serie.
     Quien la incluye ya validó `editar agenda` y que la cita esté Programada y sin fijar. --}}
@props(['booking', 'variant' => 'button'])
@php
    $chip = $variant === 'chip';
@endphp
<form method="POST" action="{{ route('agenda.series.pin', $booking) }}" class="d-inline">
    @csrf
    <button type="submit" @class(['agenda-chip agenda-chip--pin' => $chip, 'btn btn-sm text-white' => ! $chip]) @if(! $chip) style="background: #7c3aed;" @endif title="Confirmar esta cita en este día y hora">
        <i class="bi bi-pin-angle-fill"></i> Fijar
    </button>
</form>
<form method="POST" action="{{ route('agenda.series.discard', $booking) }}" class="d-inline">
    @csrf
    <button type="submit" @class(['agenda-chip agenda-chip--neutral' => $chip, 'btn btn-sm btn-outline-secondary' => ! $chip]) title="No se atenderá: se quita de la agenda" data-confirm="¿Descartar esta cita de la serie? Se quita de la agenda; la serie sigue con las demás.">
        <i class="bi bi-x-circle"></i> Descartar
    </button>
</form>
