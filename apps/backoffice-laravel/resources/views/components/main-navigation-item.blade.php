@props(['item'])

@if(empty($item['comingSoon']))
    <a href="{{ $item['route'] }}" class="dropdown-item app-dropdown-item {{ $item['active'] ? 'active' : '' }}"
       data-bs-toggle="tooltip" data-bs-placement="left"
       data-bs-title="{{ ($item['debug_id'] ?? '') . ' - ' . ($item['description'] ?? '') }}">
        <span class="app-dropdown-title">{{ $item['label'] }}</span>
        <small class="app-dropdown-description">{{ $item['description'] }}</small>
    </a>
@else
    <div class="dropdown-item app-dropdown-item disabled {{ $item['active'] ? 'active' : '' }}"
         data-bs-toggle="tooltip" data-bs-placement="left"
         data-bs-title="{{ ($item['debug_id'] ?? '') . ' - ' . ($item['description'] ?? '') }}">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
                <span class="app-dropdown-title">{{ $item['label'] }}</span>
                <small class="app-dropdown-description">{{ $item['description'] }}</small>
            </div>
            @if(!empty($item['comingSoon']))
                <span class="badge rounded-pill text-bg-light">Próx.</span>
            @endif
        </div>
    </div>
@endif
