@props([
    'paginator' => null,
    'tableClass' => 'table table-hover align-middle mb-0',
    'resizableColumns' => (bool) config('backoffice.ui.enable_resizable_tables', true),
])

<div class="catalog-table card {{ $resizableColumns ? 'catalog-table--resizable' : '' }}" @if($resizableColumns) data-resizable-columns="true" @endif>
    <div class="table-responsive">
        <table class="{{ trim($tableClass . ' catalog-table__table') }}">
            {{ $slot }}
        </table>
    </div>
</div>

@if($paginator)
    <div class="mt-3">
        {{ $paginator->links() }}
    </div>
@endif