@php
    $prevDate = $selectedDate->copy()->subMonthNoOverflow()->startOfMonth();
    $nextDate = $selectedDate->copy()->addMonthNoOverflow()->startOfMonth();
@endphp

<div class="agenda-calendar-nav mb-3">
    <a class="btn btn-sm btn-outline-dark" href="{{ route('agenda.index', array_merge(request()->except(['page', 'date']), ['date' => $prevDate->format('Y-m-d')])) }}">&laquo; Mes anterior</a>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('agenda.index', array_merge(request()->except(['page', 'date']), ['date' => now()->format('Y-m-d')])) }}">Hoy</a>
    <a class="btn btn-sm btn-outline-dark" href="{{ route('agenda.index', array_merge(request()->except(['page', 'date']), ['date' => $nextDate->format('Y-m-d')])) }}">Mes siguiente &raquo;</a>
    <span class="agenda-calendar-nav__label">{{ $operationalDateLabel }}</span>
</div>

<div class="agenda-calendar-month">
    @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dow)
        <div class="agenda-calendar-month__dow">{{ $dow }}</div>
    @endforeach
    @foreach($calendarDays as $day)
        @php
            $dayHref = route('agenda.index', array_merge(request()->except(['page']), ['cal_view' => 'day', 'date_scope' => 'custom', 'date' => $day['date']->format('Y-m-d')]));
        @endphp
        <a href="{{ $dayHref }}" class="agenda-calendar-month__cell
            {{ $day['is_outside_month'] ? 'agenda-calendar-month__cell--muted' : '' }}
            {{ $day['is_today'] ? 'agenda-calendar-month__cell--today' : '' }}">
            <span class="agenda-calendar-month__cell-number">{{ $day['date']->format('d') }}</span>
            <div class="agenda-calendar-month__chips">
                @foreach($day['items']->take(3) as $item)
                    @php $isHotel = $item->agenda_type === 'hotel'; @endphp
                    <span class="agenda-calendar-event-chip agenda-calendar-event-chip--static {{ $isHotel ? 'agenda-calendar-event-chip--hotel' : 'agenda-calendar-event-chip--spa' }}">
                        <span class="agenda-calendar-event-chip__time">{{ $item->scheduled_at?->format($timeFormat) }}</span>
                        <span class="agenda-calendar-event-chip__label">{{ $item->pet?->name ?: 'Mascota' }}</span>
                    </span>
                @endforeach
                @if($day['items']->count() > 3)
                    <span class="agenda-calendar-month__more">+{{ $day['items']->count() - 3 }} más</span>
                @endif
            </div>
        </a>
    @endforeach
</div>
