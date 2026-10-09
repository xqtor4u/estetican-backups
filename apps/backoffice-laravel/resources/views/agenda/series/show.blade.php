@php
    $screenDebugId = 'AgSerSho';

    $page = \App\Support\Pages\AgendaPage::seriesShow($series);
    $breadcrumbs = $page['breadcrumbs'];
    $isCancelled = $series->status === \App\Models\SpaBookingSeries::STATUS_CANCELLED;
    $skipped = $series->skipped_occurrences ?? [];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('agenda.series.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Series recurrentes
        </a>
        @can('ver mascotas')
            @if($series->pet)
                <a href="{{ route('pets.show', $series->pet) }}" class="btn btn-outline-dark">
                    <i class="bi bi-paw me-1"></i> Perfil de mascota
                </a>
            @endif
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
                    <span class="badge text-bg-light border">{{ $series->statusLabel() }}</span>
                    <span><span class="agenda-series-dot"></span> {{ $series->recurrenceRule()->label() }} · {{ $series->starts_at->format('H:i') }}</span>
                    <span>{{ $series->starts_at->format('d/m/Y') }} → {{ $series->ends_on->format('d/m/Y') }}</span>
                    <span>· restan {{ $series->remainingCount() }}</span>
                </div>
                <div class="small text-body-secondary">
                    {{ $series->pet?->client?->full_name }}
                    @if($series->branch) · {{ $series->branch->name }} @endif
                    @if($series->createdBy) · creada por {{ $series->createdBy->name }} el {{ $series->created_at->format('d/m/Y') }} @endif
                    @if($series->paused_from && $series->paused_until) · pausa del {{ $series->paused_from->format('d/m/Y') }} al {{ $series->paused_until->format('d/m/Y') }} @endif
                </div>
                @if(! empty($series->template['lines']))
                    <div class="small mt-2">
                        @foreach($series->template['lines'] as $line)
                            <span class="badge text-bg-light border me-1">{{ $line['service_name'] ?? 'Servicio' }} · {{ $line['duration_minutes'] }} min</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <h2 class="h6 text-uppercase letter-space mb-2">Próximas citas</h2>
        <x-list-table>
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Operador</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($upcoming as $booking)
                    <tr>
                        <td>
                            <x-series-dot :booking="$booking" />
                            <a href="{{ route('agenda.show', $booking) }}" class="fw-semibold">{{ $booking->scheduled_at->translatedFormat('D d/m/Y H:i') }}</a>
                            @if($booking->series_original_at)
                                <div class="small text-body-secondary"><i class="bi bi-shuffle"></i> Recorrida desde {{ $booking->series_original_at->format('d/m H:i') }}@if($booking->series_move_reason) — {{ $booking->series_move_reason }}@endif</div>
                            @endif
                        </td>
                        <td>{{ $booking->operator?->full_name ?? '—' }}</td>
                        <td>
                            @if(in_array($booking->status, \App\Models\SpaBooking::NOT_PERFORMED_STATUSES, true))
                                <span class="badge text-bg-light border">Cancelada</span>
                            @elseif($booking->isSeriesTentative())
                                <span class="badge rounded-pill border">Por fijar</span>
                            @else
                                <span class="badge rounded-pill text-bg-light border"><i class="bi bi-pin-angle-fill"></i> Fijada{{ $booking->seriesConfirmedBy ? ' por '.$booking->seriesConfirmedBy->name : '' }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($booking->status === 'scheduled' && $booking->isSeriesTentative())
                                @can('editar agenda')
                                    <x-series-actions :booking="$booking" />
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-4 text-body-secondary">Sin citas próximas.</td></tr>
                @endforelse
            </tbody>
        </x-list-table>

        @if($skipped)
            <h2 class="h6 text-uppercase letter-space mt-4 mb-2">Fechas sin lugar</h2>
            <ul class="small">
                @foreach($skipped as $s)
                    <li>{{ \Illuminate\Support\Carbon::parse($s['original_at'])->format('d/m/Y H:i') }} — {{ $s['reason'] }}</li>
                @endforeach
            </ul>
        @endif

        @if($past->isNotEmpty())
            <h2 class="h6 text-uppercase letter-space mt-4 mb-2">Citas anteriores</h2>
            <ul class="small">
                @foreach($past as $booking)
                    <li><a href="{{ route('agenda.show', $booking) }}">{{ $booking->scheduled_at->format('d/m/Y H:i') }}</a> — {{ $booking->status === 'completed' ? 'Atendida' : ucfirst($booking->status) }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="col-lg-4">
        @unless($isCancelled)
            <div class="card shadow-sm border-0 rounded-4 mb-3">
                <div class="card-body p-4">
                    <h3 class="h6 mb-2">Extender vigencia</h3>
                    <form method="POST" action="{{ route('agenda.series.extend', $series) }}">
                        @csrf
                        <x-form-label for="ends_on" :required="true">Hasta</x-form-label>
                        <input id="ends_on" type="date" name="ends_on" min="{{ $series->ends_on->copy()->addDay()->toDateString() }}" value="{{ old('ends_on') }}" class="form-control mb-2 @error('ends_on') is-invalid @enderror" required>
                        @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <p class="small text-body-secondary">Las fechas nuevas pasan por los mismos choques que el alta y quedan por fijar.</p>
                        <button type="submit" class="btn btn-primary w-100">Extender</button>
                    </form>
                </div>
            </div>

            @if($series->isOpen())
                <div class="card shadow-sm border-0 rounded-4 mb-3">
                    <div class="card-body p-4">
                        <h3 class="h6 mb-2">Pausar</h3>
                        <form method="POST" action="{{ route('agenda.series.pause', $series) }}">
                            @csrf
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <x-form-label for="paused_from" :required="true">Desde</x-form-label>
                                    <input id="paused_from" type="date" name="paused_from" min="{{ today()->toDateString() }}" value="{{ old('paused_from') }}" class="form-control @error('paused_from') is-invalid @enderror" required>
                                    @error('paused_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <x-form-label for="paused_until" :required="true">Hasta</x-form-label>
                                    <input id="paused_until" type="date" name="paused_until" min="{{ today()->toDateString() }}" value="{{ old('paused_until') }}" class="form-control @error('paused_until') is-invalid @enderror" required>
                                    @error('paused_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <p class="small text-body-secondary">Las citas de ese rango salen de la agenda y liberan operador y jaula. Después de la pausa la serie sigue sola.</p>
                            <button type="submit" class="btn btn-outline-primary w-100" data-confirm="¿Pausar la serie en ese rango? Las citas de esas fechas se quitarán de la agenda.">Pausar</button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card shadow-sm border-0 rounded-4 mb-3">
                <div class="card-body p-4">
                    <h3 class="h6 mb-2">Cancelar de aquí en adelante</h3>
                    <form method="POST" action="{{ route('agenda.series.cancel', $series) }}">
                        @csrf
                        <x-form-label for="cancel_from" :required="true">A partir del</x-form-label>
                        <input id="cancel_from" type="date" name="cancel_from" min="{{ today()->toDateString() }}" value="{{ old('cancel_from', today()->toDateString()) }}" class="form-control mb-2 @error('cancel_from') is-invalid @enderror" required>
                        @error('cancel_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <p class="small text-body-secondary">Las citas sin fijar se borran; las fijadas se cancelan. Las que tengan presupuesto o pago no se tocan.</p>
                        <button type="submit" class="btn btn-outline-danger w-100" data-confirm="¿Cancelar la serie desde esa fecha? No se puede deshacer.">Cancelar serie</button>
                    </form>
                </div>
            </div>
        @endunless

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <h3 class="h6 mb-2">Historial</h3>
                <ul class="list-unstyled small mb-0">
                    @forelse($series->events->take(30) as $event)
                        <li class="mb-1">
                            <span class="text-body-secondary">{{ $event->created_at->format('d/m H:i') }}</span>
                            · {{ $event->label() }}
                            @if($event->scheduled_at) ({{ $event->scheduled_at->format('d/m H:i') }}) @endif
                            @if($event->user) · {{ $event->user->name }} @endif
                            @if($event->details)<div class="text-body-secondary">{{ $event->details }}</div>@endif
                        </li>
                    @empty
                        <li class="text-body-secondary">Sin movimientos todavía.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
