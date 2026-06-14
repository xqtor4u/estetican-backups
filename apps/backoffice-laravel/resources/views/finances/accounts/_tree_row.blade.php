@php
    $typeLabels = ['activo' => 'Activo', 'pasivo' => 'Pasivo', 'capital' => 'Capital', 'ingreso' => 'Ingreso', 'gasto' => 'Gasto'];
    $typeBadge  = ['activo' => 'primary', 'pasivo' => 'warning', 'capital' => 'info', 'ingreso' => 'success', 'gasto' => 'danger'];
@endphp

<tr class="{{ $account->is_active ? '' : 'table-secondary opacity-60' }}">
    <td>
        <span style="padding-left: {{ $depth * 1.5 }}rem;" class="d-inline-block">
            @if($depth > 0)<span class="text-body-tertiary me-1">└</span>@endif
            <code class="fw-semibold">{{ $account->code }}</code>
        </span>
    </td>
    <td>
        <span class="{{ $account->allows_entries ? '' : 'fw-semibold text-body-secondary' }}">
            {{ $account->name }}
        </span>
        @if(!$account->allows_entries)
            <span class="badge rounded-pill bg-light text-dark border ms-1" style="font-size:.7rem;">Agrupadora</span>
        @endif
    </td>
    <td>
        <span class="badge rounded-pill text-bg-{{ $typeBadge[$account->type] ?? 'secondary' }}">
            {{ $typeLabels[$account->type] ?? $account->type }}
        </span>
    </td>
    <td>
        <span class="catalog-status-badge {{ $account->is_active ? 'catalog-status-badge--active' : 'catalog-status-badge--inactive' }}">
            {{ $account->is_active ? 'Activa' : 'Inactiva' }}
        </span>
    </td>
    <td class="text-center">
        {{ $account->allows_entries ? '✓' : '—' }}
    </td>
    <td class="text-end">
        <div class="d-flex gap-1 justify-content-end">
            <a href="{{ route('finances.accounts.edit', $account) }}" class="btn btn-sm btn-outline-warning">Editar</a>
            <form action="{{ route('finances.accounts.destroy', $account) }}" method="POST" class="d-inline">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger"
                    data-confirm="¿Eliminar la cuenta {{ $account->code }}?">Eliminar</button>
            </form>
        </div>
    </td>
</tr>

@foreach($account->children as $child)
    @include('finances.accounts._tree_row', ['account' => $child, 'depth' => $depth + 1])
@endforeach
