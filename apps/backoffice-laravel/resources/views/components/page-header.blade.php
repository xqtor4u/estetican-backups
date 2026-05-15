@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

<section class="page-header mb-3">
    <div class="page-header-body">
        <div>
            @if($eyebrow)
                <span class="page-header-eyebrow">{{ $eyebrow }}</span>
            @endif

            <h1 class="page-header-title mb-1">{{ $title }}</h1>

            @if($subtitle)
                <p class="page-header-subtitle mb-0">{{ $subtitle }}</p>
            @endif
        </div>

        @if(isset($actions))
            <div class="page-header-actions">
                {{ $actions }}
            </div>
        @endif
    </div>
</section>