{{-- ZEUS-047: punto de serie recurrente. Sólido = cita fijada (confirmada); contorno =
     pre-programada (aparta horario, sin recordatorios, falta "Fijar"); 🔀 = la cita se recorrió
     de su fecha. --}}
@props(['booking'])
@php
    $series = ($booking->series_id ?? null) ? $booking->series : null;
@endphp
@if($series)
    @php
        $pending = $booking->isSeriesTentative();
        $moved = (bool) $booking->series_original_at;
        $title = collect([
            $pending ? 'Serie recurrente · pre-programada (falta fijar)' : 'Serie recurrente · cita fijada',
            $series->recurrenceRule()->label(),
            $moved ? 'Recorrida desde '.$booking->series_original_at->format('d/m H:i').($booking->series_move_reason ? ' — '.$booking->series_move_reason : '') : null,
        ])->filter()->implode(' · ');
    @endphp
    <span {{ $attributes->class(['agenda-series-dot', 'agenda-series-dot--pending' => $pending]) }} title="{{ $title }}" aria-label="{{ $title }}"></span>@if($moved)<i class="bi bi-shuffle agenda-series-moved" title="{{ $title }}" aria-hidden="true"></i>@endif
@endif
