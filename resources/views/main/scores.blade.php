@php
    use App\Enums\CountdownModes;
    use App\Enums\GameModes;
    use App\Enums\LetterSets;
    use App\Enums\LobbyFormats;
    use App\Http\Controllers\ScoreController;

    $multi = $tab === ScoreController::TAB_MULTI;
    $groups = [
        ...($multi
            ? ['format' => ['Formato', ['' => 'Todos'] + collect(LobbyFormats::cases())->mapWithKeys(fn ($format) => [$format->value => $format->shortLabel()])->all()]]
            : ['mode' => ['Modo', ['' => 'Todos', GameModes::CONTRARELOJ->value => 'Contrarreloj', GameModes::INFINITO->value => 'Sin límite']]]),
        'letter_set' => ['Letras', ['' => 'Todas', LetterSets::SIMPLE->value => 'Simples', LetterSets::FULL->value => 'Las 20 letras']],
        'ordered' => ['Orden', ['' => 'Todos', '0' => 'Cualquier orden', '1' => 'En orden']],
    ];
    $times = ['' => 'Todas'] + collect(CountdownModes::cases())->mapWithKeys(fn ($time) => [$time->value => $time->shortLabel()])->all();
    $tabs = [ScoreController::TAB_SOLO => 'Individual', ScoreController::TAB_MULTI => 'Multijugador'];
    $tabQuery = $multi ? ['tab' => ScoreController::TAB_MULTI] : [];
    $kind = $multi ? 'multijugador' : 'individuales';
@endphp

<x-layouts.app title="Clasificación" description="Las mejores partidas de Matricular, individuales y multijugador, por modo y ajustes." noindex>
    <div class="px-6 pt-4 pb-24">
        <div class="mx-auto flex max-w-6xl flex-col gap-10">
            <h1 class="title-ink text-[clamp(2rem,8.5vw,4.5rem)]">Clasificación</h1>

            {{-- Solo and multiplayer scores never mix: each tab is its own ranking. --}}
            <nav aria-label="Tipo de partida" class="grid grid-cols-2 gap-3 sm:flex">
                @foreach ($tabs as $value => $label)
                    <a
                        href="{{ route('scores.view', $value === ScoreController::TAB_MULTI ? ['tab' => $value] : []) }}"
                        @if ($tab === $value) aria-current="page" @endif
                        @class(['btn text-2xl max-sm:px-2 max-sm:text-base', 'btn-primary' => $tab === $value])
                    >{{ $label }}</a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('scores.view') }}" x-data="{ mode: @js($filters['mode'] ?? '') }" class="paper flex flex-col gap-6 p-7" aria-label="Filtrar la clasificación">
                @if ($multi)
                    <input type="hidden" name="tab" value="{{ ScoreController::TAB_MULTI }}">
                @endif
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

                    @unless ($multi)
                        <fieldset class="flex flex-wrap items-center gap-2.5" x-show="mode === @js(GameModes::CONTRARELOJ->value)">
                            <legend class="float-left mr-2 text-xl font-black">Duración</legend>
                            @foreach ($times as $value => $label)
                                <x-option variant="chip" name="time" :value="$value" :label="$label" :checked="($filters['time'] ?? '') === $value" class="text-lg!" />
                            @endforeach
                        </fieldset>
                    @endunless
                </div>
                <div class="flex flex-wrap items-center gap-6">
                    <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                    @if ($filters !== [])
                        <a class="link-btn text-lg" href="{{ route('scores.view', $tabQuery) }}">Quitar filtros</a>
                    @endif
                </div>
            </form>

            @include('main.partials.ranked-runs', [
                'id' => 'top',
                'title' => 'Las 10 mejores partidas',
                'runs' => $topRuns,
                'empty' => 'Nadie ha puntuado todavía con estos ajustes. Juega una partida y estrena la tabla.',
            ])

            @include('main.partials.ranked-runs', [
                'id' => 'hoy',
                'title' => 'Lo mejor de hoy',
                'runs' => $todayRuns,
                'empty' => 'Hoy nadie ha puntuado todavía con estos ajustes.',
            ])

            @include('main.partials.ranked-runs', [
                'id' => 'mejores',
                'title' => 'Tus mejores partidas',
                'runs' => $bestRuns,
                'player' => false,
                'receipt' => ! $multi,
                'empty' => "Aún no tienes partidas {$kind} con estos ajustes.",
            ])

            @include('main.partials.ranked-runs', [
                'id' => 'recientes',
                'title' => 'Tus últimas partidas',
                'runs' => $recentRuns,
                'ranked' => false,
                'player' => false,
                'receipt' => ! $multi,
                'empty' => $multi ? 'Aún no has jugado ninguna partida multijugador con palabras.' : 'Aún no has terminado ninguna partida con palabras.',
            ])
        </div>
    </div>
</x-layouts.app>
