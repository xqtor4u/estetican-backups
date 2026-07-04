@php
    $isHotel = $item->agenda_type === 'hotel';
    $chipHref = $isHotel ? route('hotel-reservations.show', $item) : route('agenda.show', $item);
    $timeLabel = $item->scheduled_at?->format($timeFormat);
@endphp
<a href="{{ $chipHref }}" class="agenda-calendar-event-chip {{ $isHotel ? 'agenda-calendar-event-chip--hotel' : 'agenda-calendar-event-chip--spa' }}">
    <span class="agenda-calendar-event-chip__time">{{ $timeLabel }}</span>
    <span class="agenda-calendar-event-chip__label">{{ $item->pet?->name ?: 'Mascota' }}</span>
</a>
