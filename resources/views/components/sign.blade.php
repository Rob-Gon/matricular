@props(['type' => 'ok', 'label' => null])

@php
    $label ??= ['ok' => 'Acierto', 'fail' => 'Fallo', 'warn' => 'Aviso'][$type];
@endphp

<svg {{ $attributes->class('shrink-0') }} role="img" aria-label="{{ $label }}" viewBox="0 0 100 100">
    @if ($type === 'ok')
        <circle cx="50" cy="50" r="44" fill="#1F3A97" stroke="#000000" stroke-width="6" />
        <path d="M30 52 L44 66 L72 36" fill="none" stroke="#FFFFFF" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" />
    @elseif ($type === 'fail')
        <path d="M31 6 H69 L94 31 V69 L69 94 H31 L6 69 V31 Z" fill="#C8361F" stroke="#000000" stroke-width="6" stroke-linejoin="round" />
        <path d="M34 34 L66 66 M66 34 L34 66" stroke="#FFFFFF" stroke-width="10" stroke-linecap="round" />
    @else
        <path d="M50 8 L94 88 H6 Z" fill="#FBF8EF" stroke="#C8361F" stroke-width="12" stroke-linejoin="round" />
        <path d="M50 8 L94 88 H6 Z" fill="none" stroke="#000000" stroke-width="4" stroke-linejoin="round" />
        <path d="M50 38 V62" stroke="#000000" stroke-width="9" stroke-linecap="round" />
        <circle cx="50" cy="75" r="5" fill="#000000" />
    @endif
</svg>
