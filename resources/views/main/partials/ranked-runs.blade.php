{{--
    A table of games for the rankings page.
    $id, $title, $empty: heading id, heading and the text when there are no games.
    $runs: the games (with "user" loaded, and "lobbyMatch" for multiplayer ones).
    $ranked: show the position. $player: show who played. $receipt: link each solo game to its receipt.
--}}
@php
    $ranked ??= true;
    $player ??= true;
    $receipt ??= false;
@endphp

<section aria-labelledby="{{ $id }}" class="paper flex flex-col gap-5 p-7">
    <h2 id="{{ $id }}" class="font-display text-3xl">{{ $title }}</h2>
    @if ($runs->isEmpty())
        <p class="text-xl">{{ $empty }}</p>
    @else
        <div class="relative overflow-x-auto">
            <table class="w-full text-left text-xl">
                <thead>
                    <tr class="border-b-4 border-ink">
                        @if ($ranked)
                            <th scope="col" class="py-2 pr-4">Puesto</th>
                        @endif
                        @if ($player)
                            <th scope="col" class="py-2 pr-4">Jugador</th>
                        @endif
                        <th scope="col" class="py-2 pr-4 text-right">Puntos</th>
                        <th scope="col" class="py-2 pr-4">Partida</th>
                        <th scope="col" class="py-2 pr-4">Fecha</th>
                        @if ($receipt)
                            <th scope="col" class="py-2"><span class="sr-only">Tique</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $index => $run)
                        <tr @class(['border-b-2 border-ink/20', 'font-black' => $ranked && $index < 3, 'bg-mustard/30' => $player && $run->user_id === auth()->id()])>
                            @if ($ranked)
                                <td class="py-2.5 pr-4">
                                    <span @class(['inline-flex size-9 items-center justify-center', 'rounded-full border-[3px] border-ink bg-mustard' => $index < 3])>{{ $index + 1 }}</span>
                                </td>
                            @endif
                            @if ($player)
                                <td class="py-2.5 pr-4 break-all">
                                    {{ $run->user?->name }}
                                    @if ($run->user_id === auth()->id())
                                        <span class="text-base font-bold">(tú)</span>
                                    @endif
                                </td>
                            @endif
                            <td class="py-2.5 pr-4 text-right font-black tabular-nums">{{ number_format($run->score, 0, ',', '.') }}</td>
                            <td class="py-2.5 pr-4 text-lg font-normal">{{ implode(', ', $run->settingsLabels()) }}</td>
                            <td class="py-2.5 pr-4 text-lg font-normal whitespace-nowrap"><x-local-time :value="$run->created_at" /></td>
                            @if ($receipt)
                                <td class="py-2.5 text-right">
                                    @if ($run->isOver())
                                        <a class="font-bold underline" href="{{ route('game.summary', $run) }}">Ver tique<span class="sr-only"> de la partida del <x-local-time :value="$run->created_at" /></span></a>
                                    @else
                                        <a class="font-bold underline" href="{{ route('game.show', $run) }}">Seguir</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
