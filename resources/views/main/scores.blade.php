@php
    use App\Enums\CountdownModes;
    use App\Enums\GameModes;
    use App\Enums\LetterSets;

    $groups = [
        'mode' => ['Modo', ['' => 'Todos', GameModes::CONTRARELOJ->value => 'Contrarreloj', GameModes::INFINITO->value => 'Sin límite']],
        'letter_set' => ['Letras', ['' => 'Todas', LetterSets::SIMPLE->value => 'Simples', LetterSets::FULL->value => 'Las 20 letras']],
        'ordered' => ['Orden', ['' => 'Todos', '0' => 'Cualquier orden', '1' => 'En orden']],
    ];
    $times = ['' => 'Todas'] + collect(CountdownModes::cases())->mapWithKeys(fn ($time) => [$time->value => $time->shortLabel()])->all();
@endphp

<x-layouts.app title="Clasificación" description="Las mejores puntuaciones de Matricular, por modo y ajustes." noindex>
    <div class="px-6 pt-4 pb-24">
        <div class="mx-auto flex max-w-6xl flex-col gap-10">
            <h1 class="title-ink text-[clamp(2rem,8.5vw,4.5rem)]">Clasificación</h1>

            <form method="GET" action="{{ route('scores.view') }}" x-data="{ mode: @js($filters['mode'] ?? '') }" class="paper flex flex-col gap-6 p-7" aria-label="Filtrar la clasificación">
                <div class="flex flex-wrap gap-x-10 gap-y-6">
                    @foreach ($groups as $name => [$legend, $options])
                        <fieldset class="flex flex-wrap items-center gap-2.5">
                            <legend class="float-left mr-2 text-xl font-black">{{ $legend }}</legend>
                            @foreach ($options as $value => $label)
                                <x-option
                                    variant="chip"
                                    :name="$name"
                                    :value="$value"
                                    :label="$label"
                                    :checked="(string) ($filters[$name] ?? '') === (string) $value"
                                    :model="$name === 'mode' ? 'mode' : null"
                                    class="text-lg!"
                                />
                            @endforeach
                        </fieldset>
                    @endforeach

                    <fieldset class="flex flex-wrap items-center gap-2.5" x-show="mode === @js(GameModes::CONTRARELOJ->value)">
                        <legend class="float-left mr-2 text-xl font-black">Duración</legend>
                        @foreach ($times as $value => $label)
                            <x-option variant="chip" name="time" :value="$value" :label="$label" :checked="($filters['time'] ?? '') === $value" class="text-lg!" />
                        @endforeach
                    </fieldset>
                </div>
                <div class="flex flex-wrap items-center gap-6">
                    <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                    @if ($filters !== [])
                        <a class="link-btn text-lg" href="{{ route('scores.view') }}">Quitar filtros</a>
                    @endif
                </div>
            </form>

            <section aria-labelledby="top" class="paper flex flex-col gap-5 p-7">
                <h2 id="top" class="font-display text-3xl">Las 10 mejores marcas</h2>
                @if ($topScores->isEmpty())
                    <p class="text-xl">Nadie ha puntuado todavía con estos ajustes. Juega una partida y estrena la tabla.</p>
                @else
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-left text-xl">
                            <thead>
                                <tr class="border-b-4 border-ink">
                                    <th scope="col" class="py-2 pr-4">Puesto</th>
                                    <th scope="col" class="py-2 pr-4">Jugador</th>
                                    <th scope="col" class="py-2 pr-4 text-right">Mejor marca</th>
                                    <th scope="col" class="py-2 text-right">Partidas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topScores as $index => $score)
                                    <tr @class(['border-b-2 border-ink/20', 'font-black' => $index < 3, 'bg-mustard/30' => $score->id === auth()->id()])>
                                        <td class="py-2.5 pr-4">
                                            @if ($index < 3)
                                                <span class="inline-flex size-9 items-center justify-center rounded-full border-[3px] border-ink bg-mustard">{{ $index + 1 }}</span>
                                            @else
                                                <span class="inline-flex size-9 items-center justify-center">{{ $index + 1 }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 pr-4 break-all">
                                            {{ $score->name }}
                                            @if ($score->id === auth()->id())
                                                <span class="text-base font-bold">(tú)</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 pr-4 text-right tabular-nums">{{ number_format($score->highest_score, 0, ',', '.') }}</td>
                                        <td class="py-2.5 text-right tabular-nums">{{ $score->total_runs }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section aria-labelledby="recientes" class="paper flex flex-col gap-5 p-7">
                <h2 id="recientes" class="font-display text-3xl">Tus últimas partidas</h2>
                @if ($recentRuns->isEmpty())
                    <p class="text-xl">Aún no has terminado ninguna partida con palabras. <a class="font-bold underline" href="{{ route('game.setup') }}">Arranca una</a>.</p>
                @else
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-left text-xl">
                            <thead>
                                <tr class="border-b-4 border-ink">
                                    <th scope="col" class="py-2 pr-4">Fecha</th>
                                    <th scope="col" class="py-2 pr-4">Ajustes</th>
                                    <th scope="col" class="py-2 pr-4 text-right">Puntos</th>
                                    <th scope="col" class="py-2"><span class="sr-only">Tique</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentRuns as $run)
                                    <tr class="border-b-2 border-ink/20">
                                        <td class="py-2.5 pr-4 whitespace-nowrap"><x-local-time :value="$run->created_at" /></td>
                                        <td class="py-2.5 pr-4">{{ implode(', ', $run->settingsLabels()) }}</td>
                                        <td class="py-2.5 pr-4 text-right font-black tabular-nums">{{ $run->score }}</td>
                                        <td class="py-2.5 text-right">
                                            @if ($run->isOver())
                                                <a class="font-bold underline" href="{{ route('game.summary', $run) }}">Ver tique<span class="sr-only"> de la partida del <x-local-time :value="$run->created_at" /></span></a>
                                            @else
                                                <a class="font-bold underline" href="{{ route('game.show', $run) }}">Seguir</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
