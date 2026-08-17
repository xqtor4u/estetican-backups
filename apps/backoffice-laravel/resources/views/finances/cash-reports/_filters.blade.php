{{--
    Formulario de filtros compartido por los reportes que aceptan período/sucursal
    (Resumen, Métodos de pago, Por operador, Cierre de turno) — "Pendientes por cobrar" no lo
    incluye porque es una lista viva sin selector de período (ver CashReportService).
--}}
<form method="GET" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label class="form-label small text-body-secondary mb-1">Desde</label>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small text-body-secondary mb-1">Hasta</label>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
    </div>
    @if($canSelectBranch)
        <div class="col-auto">
            <label class="form-label small text-body-secondary mb-1">Sucursal</label>
            <select name="branch_id" class="form-select form-select-sm">
                <option value="">Todas las sucursales</option>
                @foreach(\App\Models\Branch::orderBy('name')->get(['id', 'name']) as $branch)
                    <option value="{{ $branch->id }}" @selected((int) request('branch_id') === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
    </div>
</form>
