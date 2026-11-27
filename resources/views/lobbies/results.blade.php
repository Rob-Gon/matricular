@php
    $standings = $report['standings'];
    $finished = $match->isFinished();
    $ordinal = fn (int $position) => $position.(in_array($position, [1, 3], true) ? '.er' : '.º');
    // Podium order on screen: 2nd, 1st, 3rd.
    $steps = [1 => ['h-36', 'bg-[#D9DBE1]'], 0 => ['h-52', 'bg-mustard'], 2 => ['h-28', 'bg-[#C98A5A]']];
    $settings = implode(', ', $match->labels());
@endphp

<x-layouts.app title="Resultados de la partida" description="Resultados de una partida multijugador de Matricular." noindex>
    <div x-data="raceResults(@js($config))">
        <section aria-labelledby="meta" class="flex flex-col items-center gap-7 px-6 pt-6 pb-0">
            <div class="flex items-center gap-4">
                <svg aria-hidden="true" viewBox="0 0 60 60" class="size-14 shrink-0 sm:size-16">
                    <path d="M10 56V6" stroke="#000000" stroke-width="5" stroke-linecap="round" />
                    <rect x="12" y="6" width="40" height="28" fill="#FBF8EF" stroke="#000000" stroke-width="4" />
                    <path d="M12 6h10v7H12zM32 6h10v7H32zM22 13h10v7H22zM42 13h10v7H42zM12 20h10v7H12zM32 20h10v7H32zM22 27h10v7H22zM42 27h10v7H42z" fill="#000000" />
                </svg>
                <h1 id="meta" class="title-ink text-[clamp(2.75rem,8vw,4.75rem)]">{{ $finished ? '¡Meta!' : 'Llegando a meta' }}</h1>
            </div>
            <p class="text-center text-xl font-bold sm:text-2xl">{{ $settings }} · {{ count($standings) }} jugadores</p>

            @unless ($finished)
                <p role="status" class="paper surface-light px-5 py-3 text-xl font-bold">Esperando a que el resto llegue a meta. Los resultados se actualizan solos.</p>
            @endunless

            @foreach ($settled as $past)
                <p class="text-center text-xl font-bold">
                    Desempate por el {{ $ordinal($past->position) }} puesto:
                    {{ $past->winner ? 'ganó '.$past->winner->name.' con «'.$past->winning_word.'».' : 'nadie acertó a tiempo y comparten puesto.' }}
                </p>
            @endforeach

            @if ($tiebreak)
                {{-- Versus: only the tied players answer; everyone else watches. --}}
                <div role="status" class="flex w-full max-w-2xl flex-col items-center gap-4 rounded-2xl border-4 border-ink bg-mustard px-6 py-5 text-center text-ink shadow-hard">
                    <p class="text-2xl leading-tight font-black">
                        Empate por el {{ $ordinal($tiebreak->position) }} puesto: {{ collect($tiedNames)->join(', ', ' y ') }} juegan una matrícula de desempate.
                    </p>
                    <p x-show="!tiebreakOn" class="title-ink text-5xl" x-text="tiebreakCountdown"></p>
                    {{-- The plate is only in the page once the tiebreak has started: the page reloads at the start. --}}
                    @if (! $tiebreak->starts_at->isFuture())
                    <div class="flex flex-col items-center gap-4">
                        <x-plate :letters="$tiebreak->plate" :digits="str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT)" scale="0.8" />
                        <p class="text-xl font-bold"><span x-text="tiebreakSeconds"></span> s para acertar. Gana la primera palabra válida.</p>
                        @if ($tiebreak->includes(auth()->user()))
                            <form class="surface-light flex w-full flex-wrap items-stretch gap-3" @submit.prevent="answerTiebreak()">
                                <label for="tiebreak-word" class="sr-only">Tu palabra para el desempate</label>
                                <input id="tiebreak-word" x-ref="tiebreakWord" x-model="tiebreakWord" class="word-input min-w-0 flex-[999_1_240px]" type="text" maxlength="50" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Escribe aquí" @paste.prevent>
                                <button type="submit" class="btn flex-[1_1_150px] text-xl" :disabled="busy">Comprobar</button>
                            </form>
                            <p class="text-xl font-bold" x-show="tiebreakMessage" x-text="tiebreakMessage" aria-live="polite"></p>
                        @else
                            <p class="text-xl font-bold">Mira quién acierta primero.</p>
                        @endif
                    </div>
                    @endif
                </div>
            @endif

            <ol aria-label="Podio" class="mt-3 flex items-end">
                @foreach ($steps as $index => [$height, $color])
                    @isset($standings[$index])
                        @php($row = $standings[$index])
                        <li class="flex w-[min(14rem,30vw)] flex-col items-center gap-2">
                            <svg aria-hidden="true" viewBox="0 0 64 96" class="h-20 w-14">
                                <rect x="4" y="12" width="10" height="20" rx="4" fill="#000000" /><rect x="50" y="12" width="10" height="20" rx="4" fill="#000000" />
                                <rect x="4" y="64" width="10" height="20" rx="4" fill="#000000" /><rect x="50" y="64" width="10" height="20" rx="4" fill="#000000" />
                                <rect x="9" y="4" width="46" height="88" rx="18" fill="{{ $row['color'] }}" stroke="#000000" stroke-width="5" />
                                <rect x="16" y="22" width="32" height="18" rx="6" fill="#9FC4BE" stroke="#000000" stroke-width="4" />
                                <rect x="18" y="62" width="28" height="12" rx="4" fill="#9FC4BE" stroke="#000000" stroke-width="4" />
                            </svg>
                            <span class="max-w-full truncate px-1 text-2xl font-black text-enamel [text-shadow:2px_2px_0_#000]">{{ $row['name'] }}</span>
                            <span class="text-xl font-bold">{{ $row['score'] }} puntos</span>
                            <div class="{{ $height }} {{ $color }} flex w-full justify-center rounded-t-[10px] border-[5px] border-b-0 border-ink pt-3.5">
                                <span class="font-display text-5xl leading-none text-ink sm:text-6xl">{{ $row['position'] }}</span>
                            </div>
                        </li>
                    @endisset
                @endforeach
            </ol>
        </section>

        <section aria-label="Resultados" class="grain surface-light border-t-8 border-ink bg-celluloid px-6 pt-14 pb-20 text-ink">
            <div class="mx-auto flex max-w-6xl flex-wrap items-start gap-12">
                <div class="flex min-w-0 flex-[1_1_380px] flex-col gap-4">
                    <h2 class="font-display text-3xl">Clasificación</h2>
                    <ol class="overflow-hidden rounded-[18px] border-4 border-ink bg-enamel shadow-hard">
                        @foreach ($standings as $row)
                            <li @class([
                                'grid grid-cols-[2.5rem_1.5rem_minmax(0,1fr)_auto_auto] items-center gap-3 border-b-2 border-ink/15 px-4 py-2.5 text-[1.375rem] last:border-b-0',
                                'bg-[#F4D98A]' => $row['id'] === $run->user_id,
                            ])>
                                <span class="font-black">{{ $row['position'] }}.º</span>
                                <span aria-hidden="true" class="size-5 rounded-full border-[3px] border-ink" style="background: {{ $row['color'] }}"></span>
                                <span class="truncate font-bold">{{ $row['name'] }}{{ $row['id'] === $run->user_id ? ' (tú)' : '' }}</span>
                                <span class="text-lg">{{ $row['words'] }} {{ $row['words'] === 1 ? 'palabra' : 'palabras' }}</span>
                                <span class="font-black tabular-nums">{{ $row['score'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="flex min-w-0 flex-[1_1_520px] flex-col gap-4">
                    <h2 class="font-display text-3xl">Lo mejor de la carrera</h2>
                    @if ($report['highlights'] === [])
                        <p class="text-xl">Nadie ha acertado ninguna palabra en esta carrera.</p>
                    @else
                        <dl class="grid grid-cols-[repeat(auto-fill,minmax(min(15rem,100%),1fr))] gap-4.5">
                            @foreach ($report['highlights'] as $highlight)
                                <div class="flex flex-col gap-1 rounded-[18px] border-4 border-ink bg-enamel px-4.5 py-4 shadow-hard">
                                    <dt class="text-lg font-bold">{{ $highlight['label'] }}</dt>
                                    <dd class="text-[1.75rem] leading-tight font-black [overflow-wrap:anywhere]">{{ $highlight['value'] }}</dd>
                                    <dd class="text-xl">{{ $highlight['name'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-4.5">
                        <a class="btn btn-primary text-2xl" href="{{ route('lobbies.show', $lobby) }}">Revancha</a>
                        <form method="POST" action="{{ route('lobbies.leave', $lobby) }}">
                            @csrf
                            <button type="submit" class="btn text-2xl">Salir de la sala</button>
                        </form>
                        {{-- The race music carries on here; this is where to turn it down or off. --}}
                        <x-sound-controls x-init="$store.sound.resumeMusic()" />
                    </div>
                    <p class="text-xl">«Revancha» vuelve a la parrilla con los mismos jugadores y reglas.</p>
                </div>
            </div>
        </section>
    </div>
</x-layouts.app>
