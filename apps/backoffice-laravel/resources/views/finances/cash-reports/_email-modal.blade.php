{{--
    Modal reusable para "Enviar por correo" — espera $emailModalId (string única) y
    $emailRoute (URL del endpoint xxxEmail). Reenvía los mismos filtros de la URL actual
    (date_from/date_to/branch_id) como campos ocultos, para que el PDF enviado por correo
    coincida con lo que se está viendo en pantalla.
--}}
<div class="modal fade" id="{{ $emailModalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ $emailRoute }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="date_from" value="{{ request('date_from') }}">
            <input type="hidden" name="date_to" value="{{ request('date_to') }}">
            <input type="hidden" name="branch_id" value="{{ request('branch_id') }}">
            <div class="modal-header">
                <h5 class="modal-title">Enviar reporte por correo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Correo destino</label>
                <input type="email" name="email" required class="form-control" placeholder="correo@ejemplo.com">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Enviar</button>
            </div>
        </form>
    </div>
</div>
