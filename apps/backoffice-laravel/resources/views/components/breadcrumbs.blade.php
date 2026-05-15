@props(['items' => []])

@if(!empty($items))
    <nav aria-label="breadcrumb" class="app-breadcrumb-wrap">
        <ol class="breadcrumb app-breadcrumb mb-0">
            @foreach($items as $item)
                @php
                    $isCurrent = (bool) ($item['current'] ?? false);
                    $label = $item['label'] ?? '';
                    $url = $item['url'] ?? null;
                @endphp

                <li class="breadcrumb-item {{ $isCurrent ? 'active' : '' }}" @if($isCurrent) aria-current="page" @endif>
                    @if(!$isCurrent && $url)
                        <a href="{{ $url }}">{{ $label }}</a>
                    @else
                        <span>{{ $label }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif