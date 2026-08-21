@props(['clientId', 'phone'])

{{-- Ícono junto a un teléfono real del cliente: ofrece mandar un mensaje directo (chat vacío)
     o elegir una plantilla de contexto "cliente" (Configuración → WhatsApp → Plantillas) que
     llega ya preformateada con los datos del cliente. Mismos endpoints que usa la app móvil
     (App\Http\Controllers\Api\ClientWhatsAppController), expuestos también por sesión web. --}}
<div
    class="d-inline-block position-relative ms-2"
    x-data="whatsappPhoneLink({
        phone: '{{ $phone }}',
        templatesUrl: '{{ route('clients.whatsapp.templates') }}',
        linkUrl: '{{ route('clients.whatsapp.link', $clientId) }}',
    })"
    @click.outside="open = false"
>
    <button type="button" class="btn btn-sm btn-outline-success" @click="toggle()">
        <i class="bi bi-whatsapp me-1"></i> WhatsApp
    </button>

    <div class="dropdown-menu shadow-sm p-2" :class="{ show: open }" style="min-width: 240px;" @click.stop>
        <button type="button" class="dropdown-item" :disabled="sending" @click="send(null)">Mensaje directo</button>

        <div class="px-2 py-1 small text-body-secondary" x-show="!loaded">Cargando plantillas…</div>

        <div class="dropdown-divider" x-show="loaded && templates.length"></div>

        <template x-for="t in templates" :key="t.id">
            <button type="button" class="dropdown-item" :disabled="sending" @click="send(t.id)" x-text="t.name"></button>
        </template>

        <div class="px-2 py-1 small text-body-secondary" x-show="loaded && templates.length === 0">
            Sin plantillas de mensaje directo configuradas.
        </div>

        <div class="px-2 py-1 small text-danger" x-show="error" x-text="error"></div>
    </div>
</div>
