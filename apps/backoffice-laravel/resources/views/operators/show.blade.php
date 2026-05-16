@php
    $screenDebugId = 'OpeSho';
    use App\Support\Pages\OperatorsPage;

    $page = OperatorsPage::show($operator);
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('operators.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
        <a href="{{ route('operators.edit', $operator) }}" class="btn btn-secondary">Editar operador</a>
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    @if($operator->profile_photo_url)
                        <img src="{{ $operator->profile_photo_url }}" alt="Foto de {{ $operator->full_name ?: $operator->name }}" class="rounded-4 border app-avatar-lg app-media-cover">
                    @else
                        <div class="rounded-4 border d-flex align-items-center justify-content-center text-body-secondary bg-body-tertiary app-avatar-lg app-avatar-placeholder app-avatar-placeholder-lg">
                            {{ \Illuminate\Support\Str::of($operator->full_name ?: $operator->name)->trim()->substr(0, 1)->upper() }}
                        </div>
                    @endif

                    <div>
                        <h2 class="h4 mb-1">{{ $operator->full_name ?: $operator->name }}</h2>
                        <div class="text-body-secondary">Clave {{ $operator->code }}</div>
                    </div>
                </div>

                <dl class="row mb-0">
                    <dt class="col-sm-4">Nombre completo</dt>
                    <dd class="col-sm-8">{{ $operator->full_name ?: $operator->name }}</dd>

                    <dt class="col-sm-4">Clave</dt>
                    <dd class="col-sm-8">{{ $operator->code }}</dd>

                    <dt class="col-sm-4">INE</dt>
                    <dd class="col-sm-8">{{ $operator->ine_number ?: 'Sin dato' }}</dd>

                    <dt class="col-sm-4">IMSS</dt>
                    <dd class="col-sm-8">{{ $operator->imss_number ?: 'Sin dato' }}</dd>

                    <dt class="col-sm-4">Teléfono</dt>
                    <dd class="col-sm-8">{{ $operator->phone ?: 'Sin dato' }}</dd>

                    <dt class="col-sm-4">Dirección</dt>
                    <dd class="col-sm-8">{{ $operator->address ?: 'Sin dato' }}</dd>

                    <dt class="col-sm-4">Contacto emergencia</dt>
                    <dd class="col-sm-8">
                        @if($operator->emergency_contact_name || $operator->emergency_contact_phone)
                            {{ $operator->emergency_contact_name ?: 'Sin nombre' }}
                            @if($operator->emergency_contact_phone)
                                · {{ $operator->emergency_contact_phone }}
                            @endif
                        @else
                            Sin dato
                        @endif
                    </dd>

                    <dt class="col-sm-4">Fecha de contratación</dt>
                    <dd class="col-sm-8">{{ $operator->hire_date?->format('Y-m-d') ?: 'Sin dato' }}</dd>

                    <dt class="col-sm-4">Rol legado</dt>
                    <dd class="col-sm-8">{{ $operator->role ?: 'Sin rol definido' }}</dd>

                    <dt class="col-sm-4">Roles activos</dt>
                    <dd class="col-sm-8">
                        @if($operator->activeRoles()->isNotEmpty())
                            {{ $operator->activeRoles()->pluck('name')->implode(', ') }}
                        @else
                            Sin roles activos.
                        @endif
                    </dd>

                    <dt class="col-sm-4">Base de operación</dt>
                    <dd class="col-sm-8">{{ optional($operator->primaryBranch())->name ?: 'Sin base asignada' }}</dd>

                    <dt class="col-sm-4">Pago por hora</dt>
                    <dd class="col-sm-8">
                        @if($operator->effectiveHourlyRate() !== null)
                            ${{ number_format($operator->effectiveHourlyRate(), 2) }}
                        @else
                            Sin tarifa vigente.
                        @endif
                    </dd>

                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">{{ $operator->is_active ? 'Activo' : 'Inactivo' }}</dd>

                    <dt class="col-sm-4">Notas</dt>
                    <dd class="col-sm-8">{{ $operator->notes ?: 'Sin notas operativas.' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Últimos trabajos ligados</h2>
                    <span class="badge text-bg-light">{{ $operator->executed_services_count }} total</span>
                </div>

                @if($executedServices->isEmpty())
                    <p class="text-body-secondary mb-0">Todavía no hay servicios ejecutados ligados a este operador.</p>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($executedServices as $executedService)
                            <div class="list-group-item px-0">
                                <div class="fw-semibold">{{ $executedService->service_summary ?: 'Servicio ejecutado sin resumen capturado' }}</div>
                                <div class="small text-body-secondary">
                                    {{ optional($executedService->executed_at)->format('Y-m-d ' . $timeFormat) ?: 'Sin fecha' }}
                                    · ${{ number_format((float) $executedService->final_price, 2) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection