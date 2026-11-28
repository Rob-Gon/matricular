@php
    use App\Models\Run;

    // Multiplayer race: same screen, the player's run of the match, plus the live scoreboard and the traffic light.
    $race ??= null;
    $digits = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $config = [
        'plate' => $race['plate'] ?? $run->plate,
        'digits' => $digits,
        'score' => $run->score,
        'remaining' => $countdownRemaining,
        'total' => $countdownTotal,
        'startsInMs' => $race['startsInMs'] ?? 0,
        'canSkip' => $race['canSkip'] ?? true,
        'rounds' => $race['rounds'] ?? null,
        'csrf' => csrf_token(),
        'urls' => $race['urls'] ?? [
            'word' => route('game.check-word', $run),
            'plate' => route('game.change-plate', $run),
            'end' => route('game.end', $run),
            'summary' => route('game.summary', $run),
        ],
    ];
@endphp

{{-- No site header: the game fills the screen, so the word box and its buttons fit on desktop. --}}
<x-layouts.app :title="$race['title'] ?? 'Partida'" description="Partida en curso de Matricular." noindex :header="false">
    @if ($race)
        <div x-data="race(@js($race))" class="contents">
        @include('main.partials.race-lights')
    @endif
    <div
        x-data="game(@js($config))"
        @keydown.window="onKeydown($event)"
        @keyup.window="onKeyup($event)"
        @pointerdown.window="onPointerdown()"
        @first-plate.window="onFirstPlate($event.detail)"
        class="relative isolate flex flex-col items-center gap-7 overflow-x-clip pb-14 xl:min-h-dvh xl:gap-5 xl:pb-6"
    >
        <h1 class="sr-only">{{ $race['title'] ?? 'Partida' }}</h1>

        {{-- Desktop only: the car drives on a road to the horizon. Smaller screens keep the flat road band. --}}
        <x-road-scene class="absolute inset-0 -z-10 hidden size-full xl:block" />

        <div class="flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-6 pt-2 sm:gap-5 xl:max-w-7xl xl:pt-5">
            <div class="paper px-4 py-3 sm:px-5 sm:py-3.5">
                <x-odometer :value="$run->score" model="score" scale="0.9" class="max-sm:[--odometer-scale:0.62]!" />
            </div>
            <ul aria-label="Ajustes de esta partida" class="order-last flex w-full flex-wrap justify-center gap-2 sm:order-none sm:w-auto sm:gap-2.5">
                @if ($race)
                    <li class="rounded-full border-[3px] border-ink bg-mustard px-3 py-1 text-base font-black text-ink sm:px-4 sm:py-2 sm:text-xl" x-text="rounds ? `${first ? 'Matrícula' : 'Ronda'} ${round + 1} de ${rounds}` : progress"></li>
                @endif
                @foreach ($race['labels'] ?? $run->settingsLabels() as $setting)
                    <li class="rounded-full border-[3px] border-ink bg-celluloid px-3 py-1 xl:bg-enamel text-base font-bold text-ink sm:px-4 sm:py-2 sm:text-xl">{{ $setting }}</li>
                @endforeach
            </ul>
            {{-- Small screens stack the sound button under the clock, so the score and the clock share a row.
                 On desktop it hangs under the clock without taking room, so the top bar keeps one row. --}}
            <div @class(['flex items-start gap-3 max-sm:flex-col max-sm:items-end max-sm:gap-2', 'xl:relative xl:block' => $countdownTotal])>
                @if ($countdownTotal)
                    <div class="paper px-3 py-2 sm:px-4 sm:py-3">
                        <x-fuel-gauge :total="$countdownTotal" :remaining="$countdownRemaining" model="remaining" class="max-sm:w-28" />
                    </div>
                @endif
                <x-sound-controls drop="down" @class(['xl:absolute xl:top-full xl:right-0 xl:mt-3' => $countdownTotal]) />
            </div>
        </div>

        <div class="flex w-full max-w-6xl flex-wrap items-end justify-center gap-8 px-6 xl:mt-auto xl:grid xl:max-w-7xl xl:grid-cols-[minmax(0,1fr)_min(600px,calc(100dvh-330px))_minmax(0,1fr)] xl:gap-6">
            @if ($race)
                @include('main.partials.race-standings')
            @endif

            {{-- The road is drawn behind the car, so the feedback card can wrap below it on small screens. --}}
            <div class="relative w-full max-w-[600px] xl:col-start-2">
                <div class="road absolute bottom-[5%] left-1/2 h-[16.5%]! w-[200vw] -translate-x-1/2 xl:hidden" aria-hidden="true"></div>
                <x-car
                    :letters="$race['plate'] ?? $run->plate"
                    :digits="$digits"
                    drop
                    letters-model="plate"
                    digits-model="digits"
                    plate-ref="plate"
                    class="relative"
                />
            </div>

            {{-- Results are announced politely so typing is never interrupted. --}}
            <div aria-live="polite" class="min-h-0 w-full flex-[0_1_340px] sm:mb-28 sm:min-w-[260px] xl:mb-36 xl:min-w-0">
                <template x-if="feedback">
                    <div class="paper flex items-center gap-3.5 px-5 py-5">
                        <x-sign type="ok" class="size-14" x-show="feedback.kind === 'ok'" />
                        <x-sign type="fail" class="size-14" x-show="feedback.kind === 'fail'" />
                        <x-sign type="warn" class="size-14" x-show="feedback.kind === 'warn'" />
                        <p class="text-[1.375rem] leading-tight font-bold" x-text="feedback.text"></p>
                    </div>
                </template>
            </div>
        </div>

        {{-- On desktop the form is a card standing on the road. --}}
        <form @submit.prevent="submit()" class="flex w-full max-w-[820px] flex-col gap-4 px-6 xl:max-w-[720px] xl:rounded-[1.6rem] xl:border-[5px] xl:border-ink xl:bg-enamel xl:py-6 xl:text-ink xl:shadow-hard-lg xl:[--focus:var(--color-plate)]">
            <label for="word" class="font-display text-2xl text-mustard xl:text-ink">Tu palabra</label>
            <div class="flex flex-wrap items-stretch gap-4">
                <div class="min-w-0 flex-[999_1_360px] xl:basis-[300px]">
                    <input
                        id="word"
                        x-ref="word"
                        x-model="word"
                        class="word-input"
                        type="text"
                        name="word"
                        maxlength="50"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        placeholder="Escribe aquí"
                        autofocus
                        :disabled="over || waiting || locked"
                        @paste.prevent
                    >
                </div>
                <button type="submit" class="btn btn-primary flex-[1_1_180px] text-2xl" aria-keyshortcuts="Enter" :disabled="busy || over || waiting || locked">
                    Comprobar <span class="kbd max-sm:hidden" aria-hidden="true">Intro</span>
                </button>
            </div>
            @if ($race)
                @include('lobbies.partials.reactions', ['after' => '$refs.word?.focus()', 'calls' => false])
            @endif
            @if (($race['canSkip'] ?? true) || ! $countdownTotal)
            <div class="flex flex-wrap items-center justify-between gap-4">
                @if ($race['canSkip'] ?? true)
                    <button type="button" class="btn" aria-keyshortcuts="Control" :aria-keyshortcuts="skipKey" @click="skip()" :disabled="busy || over || waiting">
                        Pasamatrícula (−{{ Run::POINTS_PENALTY }}) <span class="kbd max-sm:hidden" aria-hidden="true" x-text="skipKeyLabel">Ctrl</span>
                    </button>
                @endif
                @if (! $countdownTotal)
                    <button type="button" class="btn btn-danger" @click="$refs.confirmEnd.showModal()" :disabled="over">Terminar partida</button>
                @endif
            </div>
            @endif
        </form>

        @if (! $countdownTotal)
            <dialog x-ref="confirmEnd" aria-labelledby="confirm-end-title" class="paper m-auto w-[min(28rem,calc(100vw-2rem))] p-7 backdrop:bg-black/60">
                <div class="flex flex-col gap-5">
                    <h2 id="confirm-end-title" class="font-display text-3xl">¿Terminar la partida?</h2>
                    <p class="text-xl">Tu puntuación se guarda tal como está.</p>
                    <div class="flex flex-wrap gap-4">
                        <button type="button" class="btn btn-danger" @click="$refs.confirmEnd.close(); end()">Terminar partida</button>
                        <button type="button" class="btn" @click="$refs.confirmEnd.close()">Seguir jugando</button>
                    </div>
                </div>
            </dialog>
        @endif

        {{-- Achievement toasts never take focus away from the word input. --}}
        <div role="status" aria-live="polite" class="fixed top-28 right-6 z-20 flex w-[min(340px,calc(100vw-3rem))] flex-col gap-3">
            <template x-for="toast in toasts" :key="toast.id">
                <div class="paper flex items-center gap-3.5 px-3.5 py-3">
                    {{-- Every sticker is rendered here once; the toast shows the one of the unlocked achievement. --}}
                    <span class="size-24 shrink-0 [zoom:0.67]">
                        @foreach (collect(config('achievements'))->pluck('icon')->unique() as $icon)
                            <template x-if="toast.icon === @js($icon)">
                                <x-sticker :icon="$icon" />
                            </template>
                        @endforeach
                    </span>
                    <p class="flex flex-1 flex-col">
                        <span class="text-lg font-bold">Logro desbloqueado</span>
                        <span class="font-display text-lg leading-tight" x-text="toast.name"></span>
                    </p>
                    <button type="button" class="btn btn-sm min-w-11 px-3!" aria-label="Cerrar aviso de logro" @click="dismiss(toast.id)">✕</button>
                </div>
            </template>
        </div>
    </div>
    @if ($race)
        </div>
    @endif
</x-layouts.app>
