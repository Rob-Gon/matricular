{{-- Countdown as a fuel gauge. model: Alpine expression with the seconds left; total: seconds at the start. --}}
@props(['total', 'remaining', 'model'])

@php
    $format = fn (int $seconds): string => intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
@endphp

<div
    {{ $attributes->class('fuel-gauge') }}
    x-data="{
        get left() { return Math.max(0, Math.min({{ $total }}, {{ $model }})); },
        get display() { return Math.floor(this.left / 60) + ':' + String(this.left % 60).padStart(2, '0'); },
    }"
>
    <svg aria-hidden="true" viewBox="0 0 220 132">
        <path d="M14 122 A96 96 0 0 1 206 122 Z" fill="#FBF8EF" stroke="#000000" stroke-width="7" stroke-linejoin="round" />
        <path d="M189.7 74 A92 92 0 0 1 199 118" fill="none" stroke="#C8361F" stroke-width="11" />
        <line x1="22" y1="118" x2="40" y2="118" stroke="#000000" stroke-width="6" stroke-linecap="round" />
        <line x1="45" y1="53" x2="57.5" y2="65.5" stroke="#000000" stroke-width="5" stroke-linecap="round" />
        <line x1="110" y1="28" x2="110" y2="46" stroke="#000000" stroke-width="6" stroke-linecap="round" />
        <line x1="175" y1="53" x2="162.5" y2="65.5" stroke="#000000" stroke-width="5" stroke-linecap="round" />
        <text x="34" y="104" font-size="20" font-weight="900">0</text>
        <text x="172" y="104" font-size="20" font-weight="900" text-anchor="end">{{ $format($total) }}</text>
        <g
            class="fuel-gauge-needle"
            style="transform: rotate({{ -90 + 180 * $remaining / $total }}deg)"
            :style="{ transform: `rotate(${-90 + 180 * left / {{ $total }}}deg)` }"
        >
            <path d="M104 118 L110 36 L116 118 Z" fill="#C8361F" stroke="#000000" stroke-width="4" stroke-linejoin="round" />
        </g>
        <circle cx="110" cy="118" r="13" fill="#E9A92B" stroke="#000000" stroke-width="5" />
    </svg>
    {{-- role="timer" is not announced on every tick; the label states the time left on demand. --}}
    <span class="fuel-gauge-time" role="timer" :aria-label="'Quedan ' + display" :class="left <= 10 && 'text-signal'" x-text="display">{{ $format($remaining) }}</span>
</div>
