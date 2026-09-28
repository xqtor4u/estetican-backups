{{-- A1: un documento de la cita — imprimir (botón principal) + menú con PDF y, si es compartible
     (presupuesto/recibo, nunca la orden de trabajo interna), envío al cliente por WhatsApp (link
     firmado que caduca) o por correo (PDF adjunto). --}}
@php
    // Recibe: $label, $icon, $iconClass, $printUrl, $document, $id y, opcionales, $clientEmail/$clientPhone.
    $clientEmail ??= null;
    $clientPhone ??= null;
    $shareable = in_array($document, \App\Http\Controllers\ReportController::SHAREABLE, true);
@endphp
<div class="btn-group w-100">
    <a href="{{ $printUrl }}" target="_blank" class="btn btn-light btn-sm text-start d-flex align-items-center flex-grow-1">
        <i class="bi {{ $icon }} me-2 {{ $iconClass }}"></i>
        <span>{{ $label }}</span>
    </a>
    <button type="button" class="btn btn-light btn-sm dropdown-toggle dropdown-toggle-split flex-grow-0" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <span class="visually-hidden">Más opciones de {{ $label }}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 17rem;">
        <a class="dropdown-item rounded" href="{{ route('reports.pdf', [$document, $id]) }}">
            <i class="bi bi-file-earmark-arrow-down me-2"></i>Descargar PDF
        </a>
        @if($shareable)
            @if($clientPhone)
                <a class="dropdown-item rounded" href="{{ route('reports.whatsapp', [$document, $id]) }}" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp me-2 text-success"></i>Enviar por WhatsApp
                </a>
            @else
                <span class="dropdown-item-text small text-body-secondary"><i class="bi bi-whatsapp me-2"></i>Sin celular del cliente</span>
            @endif
            <div class="dropdown-divider"></div>
            <form method="POST" action="{{ route('reports.email', [$document, $id]) }}" class="px-2 pb-1">
                @csrf
                <label class="form-label small mb-1"><i class="bi bi-envelope me-1"></i>Enviar PDF por correo</label>
                <div class="input-group input-group-sm">
                    <input type="email" name="email" class="form-control" value="{{ $clientEmail }}" placeholder="correo@cliente.com" required>
                    <button type="submit" class="btn btn-primary">Enviar</button>
                </div>
            </form>
        @endif
    </div>
</div>
