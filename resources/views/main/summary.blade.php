@php
    $isBest = $run->score > 0 && $run->score >= $best;
    $seconds = fn (int $ms) => number_format($ms / 1000, 1, ',', '.').' s';
    $chip = 'rounded-full border-[3px] border-ink px-4 py-1.5 text-[1.375rem] font-bold';
    $plates = $report['plates'];
@endphp

<x-layouts.app title="Fin del trayecto" description="Resumen de tu partida de Matricular." noindex>
    <section aria-labelledby="fin" class="flex flex-col items-center gap-11 px-6 pt-6 pb-16">
        {{-- "No entry" road sign: white disc, red ring, black ink outline and drop shadow. --}}
        <div class="flex size-[min(520px,88vw)] shrink-0 flex-col items-center justify-center gap-3.5 rounded-full border-[clamp(30px,9vw,48px)] border-signal bg-enamel p-6 text-center text-ink shadow-[0_0_0_8px_#000,inset_0_0_0_6px_#000,14px_14px_0_8px_#000]">
            <p class="hidden max-w-[85%] text-xl leading-tight font-bold sm:block">{{ implode(', ', $run->settingsLabels()) }}</p>
            <h1 id="fin" class="title-ink text-[clamp(2rem,6vw,3.4rem)] text-signal">Fin del<br>trayecto</h1>
            <x-odometer :value="$run->score" :label="$isBest ? 'puntos, tu mejor marca' : 'puntos'" scale="0.9" class="items-center" />
            @unless ($isBest)
                <p class="text-base font-bold sm:text-lg">Tu mejor marca: {{ $best }} puntos</p>
            @endunless
        </div>
        {{-- On narrow screens the sign has no room for the settings line. --}}
        <p class="-mt-4 text-center text-xl font-bold sm:hidden">{{ implode(', ', $run->settingsLabels()) }}</p>

        <div class="flex flex-wrap justify-center gap-4.5">
            <form method="POST" action="{{ route('game.start') }}">
                @csrf
                <input type="hidden" name="mode" value="{{ $run->mode }}">
                <input type="hidden" name="letter_set" value="{{ $run->letter_set->value }}">
                <input type="hidden" name="ordered" value="{{ (int) $run->ordered }}">
                <input type="hidden" name="countdown_time" value="{{ $run->countdown_time }}">
                @if ($run->isCustomCountdown())
                    <input type="hidden" name="custom_minutes" value="{{ intdiv((int) $run->countdown_seconds, 60) }}">
                @endif
                <button type="submit" class="btn btn-primary text-2xl">Otra partida</button>
            </form>
            <a class="btn text-2xl" href="{{ route('game.setup', ['mode' => $run->mode]) }}">Cambiar ajustes</a>
        </div>
    </section>

    <section aria-label="Resumen de la partida" class="grain surface-light border-t-8 border-ink bg-celluloid px-6 pt-16 pb-20 text-ink">
        <div class="mx-auto flex max-w-6xl flex-wrap items-start gap-12">
            <div class="flex min-w-0 flex-[1_1_360px] flex-col gap-10">
                <div class="flex -rotate-[1.2deg] flex-col gap-4 rounded-md border-4 border-t-[10px] border-ink bg-enamel px-5 py-6 sm:px-7 sm:py-7 shadow-hard-lg [border-top-style:dotted]">
                    <h2 class="font-display text-3xl">Tique del viaje</h2>
                    @if ($report['accuracy'] !== null)
                        <p class="flex items-center gap-4 border-b-[3px] border-dashed border-ink pb-4">
                            <span class="font-display text-5xl leading-none sm:text-6xl">{{ $report['accuracy'] }}&#8239;%</span>
                            <span class="text-[1.375rem] leading-tight font-bold">de aciertos<br>{{ $report['valid']->count() }} de {{ $report['attempted'] }} {{ $report['attempted'] === 1 ? 'palabra' : 'palabras' }}</span>
                        </p>
                    @endif
                    <dl class="grid grid-cols-[minmax(0,1fr)_minmax(0,auto)] gap-x-4 gap-y-3 text-xl sm:gap-x-6 sm:text-2xl [&_dd]:text-right [&_dd]:[overflow-wrap:anywhere]">
                        <dt>Palabras acertadas</dt><dd class="font-black">{{ $report['valid']->count() }}</dd>
                        <dt>Fallos</dt><dd class="font-black">{{ $report['failed']->count() }}</dd>
                        <dt>Pasamatrículas</dt><dd class="font-black">{{ $report['skips'] }}</dd>
                        <dt>Tiempo medio por acierto</dt><dd class="font-black">{{ $report['averageMs'] === null ? '—' : $seconds($report['averageMs']) }}</dd>
                        <dt>La más larga</dt><dd class="font-black">{{ $report['longest']?->display() ?? '—' }}</dd>
                        <dt>La más rápida</dt>
                        <dd class="font-black">{{ $report['fastest'] ? $report['fastest']->display().', '.$seconds($report['fastest']->elapsed_ms) : '—' }}</dd>
                    </dl>
                </div>

                @if ($report['failed']->isNotEmpty() || $report['repeated']->isNotEmpty())
                    <div class="flex flex-col gap-4">
                        <h2 class="font-display text-3xl">Por qué fallaron</h2>
                        <ul class="flex flex-col gap-3 text-[1.375rem]">
                            @foreach ($report['failed']->concat($report['repeated']) as $word)
                                <li class="flex flex-wrap items-center gap-3">
                                    <span @class([$chip, 'bg-signal text-white line-through' => ! $word->isRepeated(), 'bg-enamel text-muted' => $word->isRepeated()])>{{ $word->display() }}</span>
                                    <span>{{ $word->reasonLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="flex min-w-0 flex-[2_1_520px] flex-col gap-10">
                @if ($plates !== [])
                    <div class="flex flex-col gap-2">
                        <h2 class="font-display text-3xl">Matrícula a matrícula</h2>
                        <p class="mb-2 text-xl">Lo que escribiste con cada una, cuánto tardaste y otras palabras que valían.</p>
                        <ul>
                            @foreach (array_slice($plates, 0, 5) as $entry)
                                @include('main.partials.trip-plate')
                            @endforeach
                        </ul>
                        @if (count($plates) > 5)
                            <details class="group">
                                <summary class="cursor-pointer border-t-[3px] border-dashed border-ink pt-4 text-xl font-bold underline group-open:hidden">
                                    Ver {{ count($plates) - 5 === 1 ? 'la matrícula que falta' : 'las '.(count($plates) - 5).' matrículas que faltan' }}
                                </summary>
                                <ul>
                                    @foreach (array_slice($plates, 5) as $entry)
                                        @include('main.partials.trip-plate')
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                @elseif ($report['valid']->isNotEmpty())
                    {{-- Runs played before attempts were recorded per plate. --}}
                    <div class="flex flex-col gap-4">
                        <h2 class="font-display text-3xl">Tus palabras</h2>
                        <ul aria-label="Palabras aceptadas" class="flex flex-wrap gap-2.5">
                            @foreach ($report['valid'] as $word)
                                <li class="{{ $chip }} bg-enamel">{{ $word->display() }}</li>
                            @endforeach
                        </ul>
                    </div>
                @elseif ($report['attempted'] === 0)
                    <p class="text-xl">No has probado ninguna palabra en esta partida.</p>
                @endif

                @if ($achievements->isNotEmpty())
                    <div class="flex flex-col gap-4.5">
                        <h2 class="font-display text-3xl">Logros de esta partida</h2>
                        <div class="flex flex-wrap gap-9">
                            @foreach ($achievements as $achievement)
                                <figure class="flex items-center gap-4">
                                    <x-sticker :tilt="$loop->odd ? -8 : 6" />
                                    <figcaption class="flex flex-col gap-0.5">
                                        <strong class="font-display text-xl font-normal">{{ $achievement->name }}</strong>
                                        <span class="text-xl">{{ $achievement->description }}</span>
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    </div>
                @endif

                <a class="link-btn self-start text-xl" href="{{ route('menu.view') }}">Volver al menú</a>
            </div>
        </div>
    </section>
</x-layouts.app>
