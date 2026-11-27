@props(['value' => 0, 'label' => 'puntos', 'scale' => 1])

@php
    $text = str_pad((string) abs((int) $value), 5, '0', STR_PAD_LEFT);
    $cells = str_split((int) $value < 0 ? '-'.substr($text, 1) : $text);
@endphp

<div {{ $attributes->class('odometer') }} style="--odometer-scale: {{ $scale }}">
    <div class="odometer-drum" role="img" aria-label="{{ $value }} {{ $label }}">
        @foreach ($cells as $cell)
            <span class="odometer-cell" aria-hidden="true">{{ $cell }}</span>
        @endforeach
    </div>
    <span class="odometer-label" aria-hidden="true">{{ $label }}</span>
</div>
