{{--
    Quick reactions, the horn and the shout, for the "lobby" and "race" Alpine components (resources/js/reactions.js).
    $after: an Alpine expression run after each click (the race screen gives the focus back to the word).
    $calls: show the horn and the shout (not during a match: with 16 players they would drown the game).
--}}
@php
    use App\Models\Lobby;

    $after ??= '';
    $calls ??= true;
@endphp

<div class="flex flex-col gap-2">
    <span class="text-lg font-bold">{{ $calls ? 'Reacciona, pita y grita' : 'Reacciona' }}</span>
    <div role="group" aria-label="Reacciones" class="flex flex-wrap items-center gap-2">
        @foreach (Lobby::REACTIONS as $emoji => $label)
            <button type="button" class="reaction-btn" aria-label="{{ $label }}" title="{{ $label }}" @click="react(@js($emoji)); {{ $after }}">{{ $emoji }}</button>
        @endforeach
        @if ($calls)
        <button type="button" class="btn btn-primary btn-sm" :disabled="cooling.horn" @click="honk('horn'); {{ $after }}">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10v4h3l7 5V5L6 10z" /><path d="M16 9c1 .8 1.5 1.8 1.5 3s-.5 2.2-1.5 3M19 6.5c1.8 1.5 2.7 3.4 2.7 5.5s-.9 4-2.7 5.5" /></svg>
            ¡Pita!
        </button>
        <button type="button" class="btn btn-primary btn-sm" :disabled="cooling.shout" @click="honk('shout'); {{ $after }}">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v11H9l-5 4z" /><path d="M12 7v3.5M12 13v.01" /></svg>
            ¡Grita!
        </button>
        @endif
    </div>
    <p x-show="reactionNotice" x-text="reactionNotice" x-cloak role="status" class="text-base font-bold text-signal"></p>
</div>
