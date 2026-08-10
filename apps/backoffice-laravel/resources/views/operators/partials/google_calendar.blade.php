<div class="card shadow-sm border-0 mt-4">
    <div class="card-body">
        <h5 class="mb-3">Google Calendar</h5>
        <p class="text-muted small">
            Comparte la agenda de este operador como un calendario de Google, de un solo sentido (EstetiCAN → Google) —
            editar un evento en Google nunca modifica la cita real. El operador lo agrega desde su cuenta personal
            ("Otros calendarios" → "Suscribirse") y lo ve como una capa más en su propia app, sin abrir nada aparte.
        </p>

        <form action="{{ route('operators.google-calendar.update', $operator) }}" method="POST" class="row g-3">
            @csrf
            @method('PUT')
            <div class="col-md-6">
                <label for="google_personal_email" class="form-label">Email personal de Google</label>
                <input id="google_personal_email" type="email" name="google_personal_email" class="form-control"
                       value="{{ old('google_personal_email', $operator->google_personal_email ?? '') }}"
                       placeholder="operador@gmail.com">
                @error('google_personal_email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check">
                    <input type="hidden" name="google_calendar_share_enabled" value="0">
                    <input class="form-check-input" type="checkbox" id="google_calendar_share_enabled"
                           name="google_calendar_share_enabled" value="1"
                           {{ old('google_calendar_share_enabled', $operator->google_calendar_share_enabled ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="google_calendar_share_enabled">
                        Compartir agenda por Google Calendar
                    </label>
                </div>
            </div>
            <div class="col-12">
                @if ($operator->google_calendar_shared_at)
                    <p class="text-success small mb-2">Compartido — última vez {{ $operator->google_calendar_shared_at->format('d/m/Y H:i') }}.</p>
                @elseif ($operator->google_calendar_share_enabled)
                    <p class="text-muted small mb-2">Pendiente de compartir — se procesa en el próximo ciclo de sincronización (cada 5 minutos).</p>
                @endif
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
