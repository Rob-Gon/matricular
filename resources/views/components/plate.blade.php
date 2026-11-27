@props(['letters', 'digits' => null, 'scale' => 1, 'drop' => false])

<div
    {{ $attributes->class(['plate', 'animate-plate-drop' => $drop]) }}
    role="img"
    aria-label="Matrícula {{ trim($digits.' '.$letters) }}"
    style="--plate-scale: {{ $scale }}"
>
    <span class="plate-band" aria-hidden="true">
        <svg class="plate-stars" viewBox="0 0 40 40">
            @foreach (range(0, 11) as $star)
                <circle cx="{{ round(20 + 14 * sin($star * M_PI / 6), 2) }}" cy="{{ round(20 - 14 * cos($star * M_PI / 6), 2) }}" r="2.6" fill="#E9A92B" />
            @endforeach
        </svg>
        <span class="plate-country">E</span>
    </span>
    <span class="plate-text" aria-hidden="true">
        @if ($digits)
            <span class="plate-digits">{{ $digits }}</span>
        @endif
        <span class="plate-letters">{{ $letters }}</span>
    </span>
</div>
