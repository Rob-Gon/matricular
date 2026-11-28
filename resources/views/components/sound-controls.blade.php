{{-- Sound settings (effects, music, volume, other players' horns and shouts), stored per browser by the "sound" Alpine store. --}}
{{-- $drop: where the menu opens, "up" (button at the bottom of a card) or "down" (button in a top bar). --}}
@props(['horns' => false, 'drop' => 'up'])
<details {{ $attributes->class('relative') }} x-data @keydown.escape="$el.open = false">
    <summary class="btn btn-sm list-none [&::-webkit-details-marker]:hidden">
        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 9h4l5-4v14l-5-4H4z" />
            <path x-show="$store.sound.effects || $store.sound.music" d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12" />
            <path x-show="!$store.sound.effects && !$store.sound.music" x-cloak d="M17 9l5 6M22 9l-5 6" />
        </svg>
        Sonido
    </summary>
    <div @class(['paper absolute right-0 z-30 flex w-64 flex-col gap-4 p-5 text-ink', 'bottom-full mb-3' => $drop === 'up', 'top-full mt-3' => $drop === 'down'])>
        <label class="flex min-h-11 items-center gap-3 text-lg font-bold">
            <input type="checkbox" class="size-6 accent-plate" :checked="$store.sound.effects" @change="$store.sound.set('effects', $event.target.checked)">
            Efectos
        </label>
        <label class="flex min-h-11 items-center gap-3 text-lg font-bold" x-show="$store.sound.hasMusic">
            <input type="checkbox" class="size-6 accent-plate" :checked="$store.sound.music" @change="$store.sound.set('music', $event.target.checked)">
            Música
        </label>
        @if ($horns)
            <label class="flex min-h-11 items-center gap-3 text-lg font-bold">
                <input type="checkbox" class="size-6 accent-plate" :checked="$store.sound.horns" @change="$store.sound.set('horns', $event.target.checked)">
                Bocinas y gritos de los demás
            </label>
        @endif
        <label class="flex flex-col gap-2 text-lg font-bold">
            Volumen
            <input type="range" min="0" max="1" step="0.05" class="accent-plate" :value="$store.sound.volume" @input="$store.sound.set('volume', Number($event.target.value))">
        </label>
    </div>
</details>
