@props([
    'for' => null,
    'required' => false,
    'small' => false,
])

<label class="form-label {{ $small ? 'small mb-1' : 'fw-semibold' }}" @if($for) for="{{ $for }}" @endif>
    {{ $slot }}
    @if($required)
        <span class="text-danger" title="Obligatorio">*</span>
    @endif
</label>
