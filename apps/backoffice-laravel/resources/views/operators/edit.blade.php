@php
    $screenDebugId = 'OpeEdi';

    $page = \App\Support\Pages\OperatorsPage::edit($operator);
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
        <a href="{{ route('operators.show', $operator) }}" class="btn btn-outline-secondary">Ver detalle</a>
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form id="operator-edit-form" action="{{ route('operators.update', $operator) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="freeze_removed_role_services" id="freeze_removed_role_services" value="1">
            @include('operators.partials.form', ['submitLabel' => 'Actualizar operador'])
        </form>
    </div>
</div>

@include('operators.partials.unavailabilities', ['operator' => $operator])
@include('operators.partials.google_calendar', ['operator' => $operator])

{{-- SYNC-102 (Fase 3 de SYNC-073, §6.4): al quitar un rol que le aportaba servicios exclusivos --}}
<div class="modal fade" id="modalRoleRemovalFreeze" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Quitaste un rol que habilitaba servicios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-body-secondary mb-2">
                    {{ $operator->full_name ?? $operator->first_name }} dejará de poder hacer, por ese rol:
                </p>
                <ul id="roleRemovalFreezeServiceList" class="mb-3"></ul>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="roleRemovalFreezeChoice" id="roleRemovalFreezeKeep" value="1" checked>
                    <label class="form-check-label" for="roleRemovalFreezeKeep">
                        <strong>Congelarlos como capacidad directa</strong> — los conserva aunque pierda el rol
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="roleRemovalFreezeChoice" id="roleRemovalFreezeDrop" value="0">
                    <label class="form-check-label" for="roleRemovalFreezeDrop">Quitárselos también</label>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-dark rounded-pill px-4" id="roleRemovalFreezeConfirm">Guardar</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('operator-edit-form');
    if (!form) return;

    const freezeData = @json($roleRemovalFreezeData);
    const templateServiceIdsByRole = freezeData.templateServiceIdsByRole || {};
    const exemptServiceIds = new Set((freezeData.exemptServiceIds || []).map(String));
    const serviceNames = freezeData.serviceNames || {};

    const roleCheckboxes = () => Array.from(form.querySelectorAll('input[name="role_ids[]"]'));
    const initiallyChecked = new Set(roleCheckboxes().filter(cb => cb.checked).map(cb => cb.value));

    let confirmedChoice = null; // null = todavía sin confirmar; '1'/'0' una vez que el usuario eligió

    function computeAtRiskServiceIds() {
        const currentlyChecked = new Set(roleCheckboxes().filter(cb => cb.checked).map(cb => cb.value));
        const removedRoleIds = [...initiallyChecked].filter(id => !currentlyChecked.has(id));
        if (removedRoleIds.length === 0) return [];

        const stillCovered = new Set();
        currentlyChecked.forEach(roleId => {
            (templateServiceIdsByRole[roleId] || []).forEach(sid => stillCovered.add(String(sid)));
        });

        const atRisk = new Set();
        removedRoleIds.forEach(roleId => {
            (templateServiceIdsByRole[roleId] || []).forEach(sid => {
                const key = String(sid);
                if (!stillCovered.has(key) && !exemptServiceIds.has(key)) atRisk.add(key);
            });
        });

        return [...atRisk];
    }

    form.addEventListener('submit', function (e) {
        if (confirmedChoice !== null) {
            document.getElementById('freeze_removed_role_services').value = confirmedChoice;
            return; // ya se confirmó el diálogo — dejar pasar
        }

        const atRisk = computeAtRiskServiceIds();
        if (atRisk.length === 0) return; // nada en riesgo, guardar normal

        e.preventDefault();

        const list = document.getElementById('roleRemovalFreezeServiceList');
        list.innerHTML = '';
        atRisk.forEach(sid => {
            const li = document.createElement('li');
            li.textContent = serviceNames[sid] || ('Servicio #' + sid);
            list.appendChild(li);
        });

        const modalEl = document.getElementById('modalRoleRemovalFreeze');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    });

    document.getElementById('roleRemovalFreezeConfirm').addEventListener('click', function () {
        const choice = document.querySelector('input[name="roleRemovalFreezeChoice"]:checked');
        confirmedChoice = choice ? choice.value : '1';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRoleRemovalFreeze')).hide();
        form.submit();
    });
})();
</script>
@endpush
@endsection