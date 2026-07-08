@php
    $prevMonth = $monthAnchor->copy()->subMonthNoOverflow();
    $nextMonth = $monthAnchor->copy()->addMonthNoOverflow();
@endphp

<div class="card shadow-sm border-0 mb-3" x-data="{ showCompletadas: true, showRecordatorio: true, showRiesgo: true }">
    <div class="card-body">
        <div class="agenda-calendar-nav mb-2">
            <a class="btn btn-sm btn-outline-dark" href="{{ route('whatsapp.bandeja', ['date' => $prevMonth->format('Y-m-d')]) }}">&laquo; Mes anterior</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('whatsapp.bandeja', ['date' => now()->format('Y-m-d')]) }}">Hoy</a>
            <a class="btn btn-sm btn-outline-dark" href="{{ route('whatsapp.bandeja', ['date' => $nextMonth->format('Y-m-d')]) }}">Mes siguiente &raquo;</a>
            <span class="agenda-calendar-nav__label text-capitalize">{{ $monthLabel }}</span>
        </div>

        <div class="d-flex flex-wrap gap-3 mb-3 small">
            <label class="d-flex align-items-center gap-1 mb-0">
                <input type="checkbox" class="form-check-input" x-model="showCompletadas">
                <span class="bandeja-calendar-dot bandeja-calendar-dot--completadas"></span> Completadas
            </label>
            <label class="d-flex align-items-center gap-1 mb-0">
                <input type="checkbox" class="form-check-input" x-model="showRecordatorio">
                <span class="bandeja-calendar-dot bandeja-calendar-dot--recordatorio"></span> Recordatorio pendiente
            </label>
            <label class="d-flex align-items-center gap-1 mb-0">
                <input type="checkbox" class="form-check-input" x-model="showRiesgo">
                <span class="bandeja-calendar-dot bandeja-calendar-dot--riesgo"></span> En riesgo de no asistir
            </label>
        </div>

        <div class="agenda-calendar-month">
            @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dow)
                <div class="agenda-calendar-month__dow">{{ $dow }}</div>
            @endforeach
            @foreach($calendarDays as $day)
                <a href="{{ route('whatsapp.bandeja', ['date' => $day['date']->format('Y-m-d')]) }}" class="agenda-calendar-month__cell
                    {{ $day['is_outside_month'] ? 'agenda-calendar-month__cell--muted' : '' }}
                    {{ $day['is_today'] ? 'agenda-calendar-month__cell--today' : '' }}">
                    <span class="agenda-calendar-month__cell-number">{{ $day['date']->format('d') }}</span>
                    <div class="bandeja-calendar-dots">
                        @if($day['counts']['completadas'] > 0)
                            <span class="bandeja-calendar-dot-row" x-show="showCompletadas" x-cloak>
                                <span class="bandeja-calendar-dot bandeja-calendar-dot--completadas"></span> {{ $day['counts']['completadas'] }}
                            </span>
                        @endif
                        @if($day['counts']['recordatorio_pendiente'] > 0)
                            <span class="bandeja-calendar-dot-row" x-show="showRecordatorio" x-cloak>
                                <span class="bandeja-calendar-dot bandeja-calendar-dot--recordatorio"></span> {{ $day['counts']['recordatorio_pendiente'] }}
                            </span>
                        @endif
                        @if($day['counts']['en_riesgo'] > 0)
                            <span class="bandeja-calendar-dot-row" x-show="showRiesgo" x-cloak>
                                <span class="bandeja-calendar-dot bandeja-calendar-dot--riesgo"></span> {{ $day['counts']['en_riesgo'] }}
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>
