@php
    $isHotel = $item->agenda_type === 'hotel';
    $chipHref = $isHotel ? route('hotel-reservations.show', $item) : route('agenda.show', $item);
    $timeLabel = $item->scheduled_at?->format($timeFormat);
    $alertReason = $isHotel ? null : $item->alert_reason;
    $alertLabel = match ($alertReason) {
        'not_started' => 'No se ha iniciado',
        'overdue' => 'Sin cerrar',
        'future' => 'Fecha inválida',
        default => null,
    };
    $statusClass = $isHotel ? 'agenda-calendar-event-chip--hotel' : match ($item->status) {
        'scheduled' => 'agenda-calendar-event-chip--scheduled',
        'completed' => 'agenda-calendar-event-chip--completed',
        'work_order' => 'agenda-calendar-event-chip--work-order',
        'no_show' => 'agenda-calendar-event-chip--no-show',
        'unfulfillable' => 'agenda-calendar-event-chip--unfulfillable',
        'cancelled' => 'agenda-calendar-event-chip--cancelled',
        default => 'agenda-calendar-event-chip--spa',
    };
    $folio = $isHotel ? null : $item->order_folio;
@endphp
<a href="{{ $chipHref }}"
    class="agenda-calendar-event-chip {{ $statusClass }} {{ $alertReason ? 'agenda-calendar-event-chip--alert' : '' }} {{ $folio ? 'agenda-calendar-event-chip--with-folio' : '' }}"
    @if($alertLabel) title="{{ $alertLabel }}" @endif
>
    <span class="agenda-calendar-event-chip__time">{{ $timeLabel }}</span>
    <span class="agenda-calendar-event-chip__label">{{ $item->pet?->name ?: 'Mascota' }}</span>
    @if($folio)
        <span class="agenda-calendar-event-chip__folio">{{ $folio }}</span>
    @endif
</a>
