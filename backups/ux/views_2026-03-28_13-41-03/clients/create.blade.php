@extends('layouts.app')

@php
    use App\Support\Pages\ClientsPage;

    $page = ClientsPage::create();
    $breadcrumbs = $page['breadcrumbs'];
@endphp

@section('content')
<x-page-header
    :eyebrow="$page['header']['eyebrow']"
    :title="$page['header']['title']"
    :subtitle="$page['header']['subtitle']"
>
    <x-slot:actions>
        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">Volver a clientes</a>
    </x-slot:actions>
</x-page-header>

<form id="client-create-form" action="{{ route('clients.store') }}" method="POST" novalidate>
    @csrf
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="text-uppercase small text-body-secondary fw-semibold mb-2">Flujo recomendado</div>
            <div class="h5 mb-2">1. Cliente mínimo  2. Mascota  3. Agenda</div>
            <p class="text-body-secondary mb-0">Prioriza nombre y teléfono. Lo demás puede quedar al final para no frenar la captura operativa.</p>
        </div>
    </div>

    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div>
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Paso 1</div>
                    <h2 class="h4 mb-1">Cliente mínimo</h2>
                    <p class="text-body-secondary mb-0">Captura lo indispensable para no perder tiempo en la primera interacción.</p>
                </div>
                <span class="badge rounded-pill text-bg-light border">Nombre + teléfono</span>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label small mb-1">Nombre</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control form-control-sm" required>
                </div>
                <div class="col-lg-6">
                    <div id="phones">
                        <div class="phone-item border rounded-3 p-3 bg-body-tertiary">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Tipo</label>
                                    <select name="phones[0][type]" class="form-control" required>
                                        <option value="mobile" @selected(old('phones.0.type', 'mobile') === 'mobile')>Móvil</option>
                                        <option value="fixed" @selected(old('phones.0.type') === 'fixed')>Fijo</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small mb-1">Teléfono principal</label>
                                    <input type="text" name="phones[0][number]" value="{{ old('phones.0.number') }}" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2">Debe existir al menos un teléfono activo para guardar el cliente.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div>
                    <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Paso 2</div>
                    <h2 class="h4 mb-1">Mascotas</h2>
                    <p class="text-body-secondary mb-0">Agrega la mascota cuanto antes para poder brincar directo a agenda desde este mismo flujo.</p>
                </div>
                <button type="button" class="btn btn-outline-dark" data-client-create-action="add-pet">Agregar mascota</button>
            </div>

            <div id="pets"></div>
            <div class="form-text">Si vas a programar servicio de inmediato, captura al menos una mascota antes de guardar.</div>
        </div>
    </section>

    <details class="card shadow-sm border-0 mb-4">
        <summary class="card-body p-4 d-flex justify-content-between align-items-center gap-3" style="cursor: pointer;">
            <div>
                <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Paso 3</div>
                <h2 class="h4 mb-1">Datos complementarios</h2>
                <p class="text-body-secondary mb-0">Apellido, email, direcciones y teléfonos adicionales pueden capturarse al final sin frenar el alta inicial.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border">Opcional / posterior</span>
        </summary>

        <div class="card-body pt-0 px-4 pb-4">
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <label class="form-label small mb-1">Apellido</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control form-control-sm">
                </div>
                <div class="col-lg-6">
                    <label class="form-label small mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-sm">
                    <div class="form-text">Opcional. Si se captura, debe venir con formato válido.</div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
                <div>
                    <h3 class="h5 mb-1">Direcciones</h3>
                    <p class="text-body-secondary small mb-0">Útiles para operación, logística y contexto comercial, pero no bloquean la captura rápida.</p>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" data-client-create-action="add-address">Agregar dirección</button>
            </div>

            <div id="addresses" class="d-grid gap-2 mb-4">
                @include('shared.address-editor', [
                    'namePrefix' => 'addresses[0]',
                    'oldPrefix' => 'addresses.0',
                    'showType' => true,
                    'useCard' => true,
                    'title' => 'Dirección 1',
                    'subtitle' => 'Editar la dirección completa y sus coordenadas desde un bloque compacto.',
                    'wrapperClass' => 'address-item shadow-sm',
                    'wrapperAttributes' => ['data-address-index' => 0],
                ])
            </div>

            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
                <div>
                    <h3 class="h5 mb-1">Teléfonos adicionales</h3>
                    <p class="text-body-secondary small mb-0">Puedes agregar más números después del principal para seguimiento o contacto secundario.</p>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" data-client-create-action="add-phone">Agregar teléfono</button>
            </div>
        </div>
    </details>

    <template id="client-address-template">
        @include('shared.address-editor', [
            'namePrefix' => 'addresses[__INDEX__]',
            'showType' => true,
            'useCard' => true,
            'title' => 'Dirección __TITLE_INDEX__',
            'subtitle' => 'Editar la dirección completa y sus coordenadas desde un bloque compacto.',
            'wrapperClass' => 'address-item shadow-sm',
            'wrapperAttributes' => ['data-address-index' => '__INDEX__'],
        ])
    </template>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-outline-secondary" name="next_action" value="save">Crear cliente</button>
        <button type="submit" class="btn btn-primary" name="next_action" value="schedule_first_pet" data-client-submit-intent="schedule-first-pet">Crear y agendar servicio</button>
    </div>
</form>

@endsection
