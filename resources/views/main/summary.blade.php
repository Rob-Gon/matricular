@php
    $isBest = $run->score > 0 && $run->score >= $best;
@endphp

<x-layouts.app title="Fin del trayecto" description="Resumen de tu partida de Matricular." noindex>
    <section aria-labelledby="fin" class="flex flex-col items-center gap-11 px-6 pt-6 pb-16">
        {{-- "No entry" road sign: white disc, red ring, black ink outline and drop shadow. --}}
        <div class="flex size-[min(600px,88vw)] shrink-0 flex-col items-center justify-center gap-3.5 rounded-full border-[clamp(30px,9vw,54px)] border-signal bg-enamel p-6 text-center text-ink shadow-[0_0_0_8px_#000,inset_0_0_0_6px_#000,14px_14px_0_8px_#000]">
            <p class="hidden max-w-[85%] text-xl leading-tight font-bold sm:block">{{ implode(', ', $run->settingsLabels()) }}</p>
            <h1 id="fin" class="title-ink text-[clamp(2rem,6.4vw,3.75rem)] text-signal">Fin del<br>trayecto</h1>
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
                <button type="submit" class="btn btn-primary text-2xl">Otra partida</button>
            </form>
            <a class="btn text-2xl" href="{{ route('game.setup', ['mode' => $run->mode]) }}">Cambiar ajustes</a>
        </div>
    </section>

    <section aria-label="Resumen de la partida" class="grain surface-light border-t-8 border-ink bg-celluloid px-6 pt-16 pb-20 text-ink">
        <div class="mx-auto flex max-w-6xl flex-wrap items-start gap-12">
            <div class="flex min-w-0 flex-[1_1_360px] -rotate-[1.2deg] flex-col gap-4 rounded-md border-4 border-t-[10px] border-ink bg-enamel px-7 py-7 shadow-hard-lg [border-top-style:dotted]">
                <h2 class="font-display text-3xl">Tique del viaje</h2>
                <dl class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-6 gap-y-3 text-2xl">
                    <dt>Palabras acertadas</dt><dd class="font-black">{{ count($words) }}</dd>
                    <dt>Fallos</dt><dd class="font-black">{{ count($rejected) }}</dd>
                    <dt>Pasamatrículas</dt><dd class="font-black">{{ $skips }}</dd>
                    <dt>La más larga</dt><dd class="font-black break-all">{{ $longest ?? '—' }}</dd>
                    <dt>La más rápida</dt>
                    <dd class="font-black">
                        @if ($fastest)
                            {{ $fastest->display() }}, {{ number_format($fastest->elapsed_ms / 1000, 1, ',', '.') }} s
                        @else
                            —
                        @endif
                    </dd>
                </dl>
            </div>

            <div class="flex min-w-0 flex-[2_1_520px] flex-col gap-10">
                <div class="flex flex-col gap-4">
                    <h2 class="font-display text-3xl">Tus palabras</h2>
                    @if ($words === [] && $rejected === [])
                        <p class="text-xl">No has probado ninguna palabra en esta partida.</p>
                    @endif
                    @if ($words !== [])
                        <ul aria-label="Palabras aceptadas" class="flex flex-wrap gap-2.5">
                            @foreach ($words as $word)
                                <li class="rounded-full border-[3px] border-ink bg-enamel px-4 py-1.5 text-[1.375rem] font-bold">{{ $word }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($rejected !== [])
                        <ul aria-label="Palabras rechazadas" class="flex flex-wrap gap-2.5">
                            @foreach ($rejected as $word)
                                <li class="rounded-full border-[3px] border-ink bg-signal px-4 py-1.5 text-[1.375rem] font-bold text-white line-through">{{ $word }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

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
