{{-- letters-model / digits-model: optional Alpine expressions that keep the plate in sync with client state. --}}
@props(['letters', 'digits' => null, 'scale' => 1, 'drop' => false, 'lettersModel' => null, 'digitsModel' => null])

<div
    {{ $attributes->class(['plate', 'animate-plate-drop' => $drop]) }}
    role="img"
    aria-label="Matrícula {{ trim($digits.' '.$letters) }}"
    @if ($lettersModel) :aria-label="'Matrícula ' + {{ $digitsModel ?? "''" }} + ' ' + {{ $lettersModel }}" @endif
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
        @if ($digits || $digitsModel)
            <span class="plate-digits" @if ($digitsModel) x-text="{{ $digitsModel }}" @endif>{{ $digits }}</span>
        @endif
        <span class="plate-letters" @if ($lettersModel) x-text="{{ $lettersModel }}" @endif>{{ $letters }}</span>
    </span>
</div>
