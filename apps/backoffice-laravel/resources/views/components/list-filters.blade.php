@props([
    'action',
    'viewMode' => null,
    'resetUrl' => null,
])

<section class="catalog-filters card mb-3">
    <div class="catalog-filters__body card-body">
        <form method="GET" action="{{ $action }}" class="catalog-filters__form row g-3">
            @if($viewMode)
                <input type="hidden" name="view" value="{{ $viewMode }}">
            @endif

            {{ $slot }}

            <div class="col-lg-2 col-md-6">
                <div class="catalog-filters__actions">
                    <button type="submit" class="btn btn-primary">Aplicar</button>
                    @if($resetUrl)
                        <a href="{{ $resetUrl }}" class="btn btn-outline-secondary">Limpiar</a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</section>