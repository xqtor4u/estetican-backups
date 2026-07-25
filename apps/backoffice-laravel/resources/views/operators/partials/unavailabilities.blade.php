<div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
        <h5 class="mb-3">Bloqueos de no disponibilidad</h5>
        <p class="text-muted small">Vacaciones, permisos o cualquier periodo en que este operador no debe poder agendarse, sin importar su horario semanal.</p>

        @if ($operator->unavailabilities->isEmpty())
            <p class="text-muted">No hay bloqueos registrados.</p>
        @else
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Desde</th>
                        <th>Hasta</th>
                        <th>Motivo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($operator->unavailabilities->sortBy('starts_at') as $unavailability)
                        <tr>
                            <td>{{ $unavailability->starts_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $unavailability->ends_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $unavailability->reason ?? '—' }}</td>
                            <td class="text-end">
                                <form action="{{ route('operators.unavailabilities.destroy', [$operator, $unavailability]) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar este bloqueo?">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <hr>

        <form action="{{ route('operators.unavailabilities.store', $operator) }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Desde</label>
                <input type="datetime-local" name="starts_at" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Hasta</label>
                <input type="datetime-local" name="ends_at" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Motivo</label>
                <input type="text" name="reason" class="form-control" maxlength="255" placeholder="Vacaciones, permiso...">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">+</button>
            </div>
        </form>
        @error('starts_at') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
        @error('ends_at') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
    </div>
</div>
