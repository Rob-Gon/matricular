@props(['name', 'value', 'label', 'hint' => null, 'checked' => false, 'model' => null, 'variant' => 'opt'])

<label {{ $attributes->class($variant) }}>
    <input
        type="radio"
        name="{{ $name }}"
        value="{{ $value }}"
        class="sr-only"
        @checked($checked)
        @if ($model) x-model="{{ $model }}" @endif
    >
    @if ($variant === 'opt')
        <span class="opt-title">{{ $label }}</span>
        @if ($hint)
            <span class="opt-hint">{{ $hint }}</span>
        @endif
    @else
        {{ $label }}
    @endif
</label>
