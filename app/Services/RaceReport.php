<?php

namespace App\Services;

use App\Models\LobbyMatch;
use App\Models\RunWord;
use Illuminate\Support\Collection;

/**
 * Results of a race: the standings and the best of the race (longest, shortest and fastest word,
 * most words, best accuracy), each with the player who did it.
 */
class RaceReport
{
    /**
     * @return array{
     *     standings: list<array{id: int, name: string, color: string, score: int, position: int, words: int}>,
     *     highlights: list<array{label: string, value: string, name: string}>
     * }
     */
    public function build(LobbyMatch $match): array
    {
        $standings = $match->standings();
        $names = collect($standings)->pluck('name', 'id');
        $runs = $match->runs()->pluck('user_id', 'id');

        // Repeated words neither score nor count against accuracy, as in solo.
        $words = RunWord::whereIn('run_id', $runs->keys())->words()
            ->where(fn ($query) => $query->whereNull('reason')->orWhere('reason', '!=', RunWord::REASON_REPEATED))
            ->get(['run_id', 'input', 'form', 'valid', 'elapsed_ms']);
        $valid = $words->where('valid', true);
        $playerOf = fn (RunWord $word): string => (string) $names[$runs[$word->run_id]];

        $validCount = $valid->countBy('run_id');
        $standings = array_map(fn (array $row) => [...$row, 'words' => (int) ($validCount[$runs->search($row['id'])] ?? 0)], $standings);

        $longest = $valid->sortByDesc(fn (RunWord $word) => mb_strlen($word->display()))->first();
        $shortest = $valid->sortBy(fn (RunWord $word) => mb_strlen($word->display()))->first();
        $fastest = $valid->whereNotNull('elapsed_ms')->sortBy('elapsed_ms')->first();
        $mostWords = $validCount->sortDesc()->keys()->first();
        $accuracy = $words->groupBy('run_id')
            ->map(fn (Collection $tries) => (int) round($tries->where('valid', true)->count() / $tries->count() * 100))
            ->filter()
            ->sortDesc();

        $highlights = array_filter([
            $longest ? ['label' => 'La más larga', 'value' => $longest->display(), 'name' => $playerOf($longest)] : null,
            $fastest ? ['label' => 'La más rápida', 'value' => $fastest->display().', '.number_format($fastest->elapsed_ms / 1000, 1, ',', '.').' s', 'name' => $playerOf($fastest)] : null,
            $mostWords !== null ? ['label' => 'Más palabras', 'value' => (string) $validCount[$mostWords], 'name' => (string) $names[$runs[$mostWords]]] : null,
            $accuracy->isNotEmpty() ? ['label' => 'Mejor puntería', 'value' => $accuracy->first()."\u{202F}%", 'name' => (string) $names[$runs[$accuracy->keys()->first()]]] : null,
            $shortest ? ['label' => 'La más corta', 'value' => $shortest->display(), 'name' => $playerOf($shortest)] : null,
        ]);

        return ['standings' => $standings, 'highlights' => array_values($highlights)];
    }
}
