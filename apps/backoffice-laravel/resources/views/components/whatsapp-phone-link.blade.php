@props(['clientId', 'phone', 'petId' => null])

{{-- Ícono junto a un teléfono real del cliente: ofrece mandar un mensaje directo (chat vacío)
     o elegir una plantilla de contexto "cliente"/"general" (Configuración → WhatsApp →
     Plantillas) que llega ya preformateada con los datos del cliente. Mismos endpoints que usa
     la app móvil (App\Http\Controllers\Api\ClientWhatsAppController), expuestos también por
     sesión web. Si se pasa `:pet-id` (ej. desde la ficha de una cita, donde ya se sabe qué
     mascota es) nunca se pregunta cuál mascota usar; si no, y la plantilla elegida es de
     contexto "general" con `{mascota}`, se pregunta solo cuando el cliente tiene más de una
     mascota viva (con una sola, o ninguna, no hace falta preguntar). --}}
<div
    class="d-inline-block position-relative ms-2"
    x-data="whatsappPhoneLink({
        phone: '{{ $phone }}',
        petId: {{ $petId ? (int) $petId : 'null' }},
        templatesUrl: '{{ route('clients.whatsapp.templates') }}',
        linkUrl: '{{ route('clients.whatsapp.link', $clientId) }}',
        livePetsUrl: '{{ route('clients.whatsapp.live-pets', $clientId) }}',
    })"
    @click.outside="open = false"
>
    <button type="button" class="btn btn-sm btn-outline-success" @click="toggle()">
        <i class="bi bi-whatsapp me-1"></i> WhatsApp
    </button>

    <div class="dropdown-menu shadow-sm p-2" :class="{ show: open }" style="min-width: 240px;" @click.stop>
        <template x-if="step === 'menu'">
            <div>
                <button type="button" class="dropdown-item" :disabled="sending" @click="choose(null, null)">Mensaje directo</button>

                <div class="px-2 py-1 small text-body-secondary" x-show="!loaded">Cargando plantillas…</div>

                <div class="dropdown-divider" x-show="loaded && templates.length"></div>

                <template x-for="t in templates" :key="t.id">
                    <button type="button" class="dropdown-item" :disabled="sending" @click="choose(t.id, t.context)" x-text="t.name"></button>
                </template>

                <div class="px-2 py-1 small text-body-secondary" x-show="loaded && templates.length === 0">
                    Sin plantillas de mensaje directo configuradas.
                </div>
            </div>
        </template>

        <template x-if="step === 'pets'">
            <div>
                <p class="px-2 py-1 small text-body-secondary mb-1">¿Para cuál mascota?</p>
                <template x-for="p in livePets" :key="p.id">
                    <button type="button" class="dropdown-item" :disabled="sending" @click="choosePet(p.id)" x-text="p.name"></button>
                </template>
                <div class="dropdown-divider"></div>
                <button type="button" class="dropdown-item text-body-secondary" :disabled="sending" @click="sendWithoutPet()">Ninguna en particular</button>
                <button type="button" class="dropdown-item text-body-secondary" :disabled="sending" @click="step = 'menu'">&laquo; Volver</button>
            </div>
        </template>

        <div class="px-2 py-1 small text-danger" x-show="error" x-text="error"></div>
    </div>
</div>
