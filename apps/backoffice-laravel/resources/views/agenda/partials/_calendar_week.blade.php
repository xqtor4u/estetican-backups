@php
    $prevDate = $selectedDate->copy()->subDays(7);
    $nextDate = $selectedDate->copy()->addDays(7);
@endphp

<div class="agenda-calendar-nav mb-3">
    <a class="btn btn-sm btn-outline-dark" href="{{ route('agenda.index', array_merge(request()->except(['page', 'date']), ['date' => $prevDate->format('Y-m-d')])) }}">&laquo; Semana anterior</a>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('agenda.index', array_merge(request()->except(['page', 'date']), ['date' => now()->format('Y-m-d')])) }}">Hoy</a>
    <a class="btn btn-sm btn-outline-dark" href="{{ route('agenda.index', array_merge(request()->except(['page', 'date']), ['date' => $nextDate->format('Y-m-d')])) }}">Semana siguiente &raquo;</a>
    <span class="agenda-calendar-nav__label">{{ $operationalDateLabel }}</span>
</div>

<div class="agenda-calendar-week">
    @foreach($calendarDays as $day)
        @php
            $dayHref = route('agenda.index', array_merge(request()->except(['page']), ['cal_view' => 'day', 'date_scope' => 'custom', 'date' => $day['date']->format('Y-m-d')]));
        @endphp
        <div class="agenda-calendar-week__day {{ $day['is_today'] ? 'agenda-calendar-week__day--today' : '' }}">
            <div class="agenda-calendar-week__day-header">
                <span class="agenda-calendar-week__day-name">{{ $day['date']->translatedFormat('D') }}</span>
                <span class="agenda-calendar-week__day-number">{{ $day['date']->format('d') }}</span>
            </div>
            <div class="agenda-calendar-week__day-body">
                @forelse($day['items'] as $item)
                    @include('agenda.partials._calendar_chip', ['item' => $item])
                @empty
                    <a class="agenda-calendar-empty-link" href="{{ $dayHref }}"></a>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
