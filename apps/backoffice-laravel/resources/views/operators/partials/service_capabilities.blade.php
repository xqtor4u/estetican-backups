@php
    $originLabels = [
        'open_to_all' => ['Abierto a todos', 'bg-secondary'],
        'template' => ['Plantilla de rol', 'bg-info text-dark'],
        'grant' => ['Capacidad directa', 'bg-success'],
        'none' => ['—', 'bg-light text-dark'],
    ];
    $grouped = $serviceCapabilityRows->groupBy(fn ($r) => $r['service']->type);
@endphp

<div class="card mt-4">
    <div class="card-header">
        <h2 class="h6 mb-0">Servicios que realiza</h2>
        <p class="small text-muted mb-0">
            Lista efectiva de este operador. Los servicios marcados por
            <span class="badge bg-info text-dark">Plantilla de rol</span> vienen de sus tipos de
            operador; desmárcalos para quitárselos sólo a esta persona. Marca uno que no traiga
            para agregárselo como capacidad directa.
        </p>
    </div>
    <div class="card-body">
        @if($serviceCapabilityRows->isEmpty())
            <x-empty-state title="No hay servicios activos en el catálogo." />
        @else
            <form action="{{ route('operators.service-capabilities.update', $operator) }}" method="POST">
                @csrf
                @method('PUT')

                @foreach($grouped as $type => $rows)
                    <fieldset class="mb-3">
                        <legend class="h6 text-uppercase text-muted small mb-2">{{ $type ?: 'Sin tipo' }}</legend>
                        <div class="row g-2">
                            @foreach($rows as $row)
                                @php([$label, $cls] = $originLabels[$row['origin']] ?? $originLabels['none'])
                                <div class="col-md-6">
                                    <div class="form-check d-flex align-items-center gap-2">
                                        <input class="form-check-input" type="checkbox"
                                               name="service_ids[]" value="{{ $row['service']->id }}"
                                               id="op-svc-{{ $row['service']->id }}"
                                               @checked($row['can'])
                                               @disabled($row['locked'])>
                                        <label class="form-check-label flex-grow-1" for="op-svc-{{ $row['service']->id }}">
                                            {{ $row['service']->name }}
                                        </label>
                                        <span class="badge {{ $cls }}">{{ $label }}</span>
                                    </div>
                                    @if($row['locked'])
                                        <input type="hidden" name="service_ids[]" value="{{ $row['service']->id }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <button type="submit" class="btn btn-primary">Guardar servicios</button>
            </form>
        @endif
    </div>
</div>
