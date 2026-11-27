@php
    use App\Models\Run;

    $digits = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $config = [
        'plate' => $run->plate,
        'digits' => $digits,
        'score' => $run->score,
        'remaining' => $countdownRemaining,
        'csrf' => csrf_token(),
        'urls' => [
            'word' => route('game.check-word', $run),
            'plate' => route('game.change-plate', $run),
            'end' => route('game.end', $run),
            'summary' => route('game.summary', $run),
        ],
    ];
@endphp

<x-layouts.app title="Partida" description="Partida en curso de Matricular." noindex>
    <div
        x-data="game(@js($config))"
        @keydown.window="onKeydown($event)"
        @keyup.window="onKeyup($event)"
        @mousedown.window="skipKeyAlone = false"
        class="flex flex-col items-center gap-7 overflow-x-clip pb-14"
    >
        <h1 class="sr-only">Partida</h1>

        <div class="flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-6 pt-2 sm:gap-5">
            <div class="paper px-4 py-3 sm:px-5 sm:py-3.5">
                <x-odometer :value="$run->score" model="score" scale="0.9" class="max-sm:[--odometer-scale:0.62]!" />
            </div>
            <ul aria-label="Ajustes de esta partida" class="order-last flex w-full flex-wrap justify-center gap-2 sm:order-none sm:w-auto sm:gap-2.5">
                @foreach ($run->settingsLabels() as $setting)
                    <li class="rounded-full border-[3px] border-ink bg-celluloid px-3 py-1 text-base font-bold text-ink sm:px-4 sm:py-2 sm:text-xl">{{ $setting }}</li>
                @endforeach
            </ul>
            @if ($countdownTotal)
                <div class="paper px-3 py-2 sm:px-4 sm:py-3">
                    <x-fuel-gauge :total="$countdownTotal" :remaining="$countdownRemaining" model="remaining" class="max-sm:w-28" />
                </div>
            @endif
        </div>

        <div class="flex w-full max-w-6xl flex-wrap items-end justify-center gap-8 px-6">
            {{-- The road is drawn behind the car, so the feedback card can wrap below it on small screens. --}}
            <div class="relative w-full max-w-[600px]">
                <div class="road absolute bottom-[5%] left-1/2 h-[16.5%]! w-[200vw] -translate-x-1/2" aria-hidden="true"></div>
                <x-car
                    :letters="$run->plate"
                    :digits="$digits"
                    drop
                    letters-model="plate"
                    digits-model="digits"
                    plate-ref="plate"
                    class="relative"
                />
            </div>

            {{-- Results are announced politely so typing is never interrupted. --}}
            <div aria-live="polite" class="min-h-0 w-full flex-[0_1_340px] sm:mb-28 sm:min-w-[260px]">
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

        <form @submit.prevent="submit()" class="flex w-full max-w-[820px] flex-col gap-4 px-6">
            <label for="word" class="font-display text-2xl text-mustard">Tu palabra</label>
            <div class="flex flex-wrap items-stretch gap-4">
                <div class="min-w-0 flex-[999_1_360px]">
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
                        placeholder="Escribe y pulsa Intro"
                        autofocus
                        :disabled="over"
                        @paste.prevent
                    >
                </div>
                <button type="submit" class="btn btn-primary flex-[1_1_180px] text-2xl" aria-keyshortcuts="Enter" :disabled="busy || over">
                    Comprobar <span class="kbd max-sm:hidden" aria-hidden="true">Intro</span>
                </button>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <button type="button" class="btn" aria-keyshortcuts="Control" :aria-keyshortcuts="skipKey" @click="skip()" :disabled="busy || over">
                    Pasamatrícula (−{{ Run::POINTS_PENALTY }}) <span class="kbd max-sm:hidden" aria-hidden="true" x-text="skipKeyLabel">Ctrl</span>
                </button>
                @if (! $countdownTotal)
                    <button type="button" class="btn btn-danger" @click="$refs.confirmEnd.showModal()" :disabled="over">Terminar partida</button>
                @endif
            </div>
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
                    <span aria-hidden="true" class="flex size-16 shrink-0 -rotate-8 items-center justify-center rounded-full border-4 border-ink bg-mustard">
                        <svg viewBox="0 0 24 24" class="size-8" fill="none" stroke="#000000" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z" /><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3" /></svg>
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
</x-layouts.app>
