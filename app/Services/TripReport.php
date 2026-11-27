<?php

namespace App\Services;

use App\Models\Run;
use App\Models\RunWord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the "Fin del trayecto" receipt shows, built from the run's attempts.
 */
class TripReport
{
    /** Alternative words shown per plate ("también valían"). */
    public const EXAMPLES_SHOWN = 3;

    /**
     * @return array{
     *     valid: Collection<int, RunWord>, failed: Collection<int, RunWord>, repeated: Collection<int, RunWord>,
     *     skips: int, attempted: int, accuracy: int|null, averageMs: int|null,
     *     longest: RunWord|null, fastest: RunWord|null,
     *     plates: list<array{plate: string, digits: string, attempts: list<RunWord>, skipped: bool, examples: list<string>}>
     * }
     */
    public function build(Run $run): array
    {
        $attempts = $run->runWords()->orderBy('id')->get();
        $words = $attempts->where('kind', RunWord::KIND_WORD);
        $valid = $words->where('valid', true)->values();
        // Repeated words neither score nor count against accuracy.
        $scored = $words->filter(fn (RunWord $word) => $word->reason !== RunWord::REASON_REPEATED);
        $timed = $valid->whereNotNull('elapsed_ms');

        return [
            'valid' => $valid,
            'failed' => $scored->where('valid', false)->values(),
            'repeated' => $words->where('reason', RunWord::REASON_REPEATED)->values(),
            'skips' => $attempts->where('kind', RunWord::KIND_SKIP)->count(),
            'attempted' => $scored->count(),
            'accuracy' => $scored->isEmpty() ? null : (int) round($valid->count() / $scored->count() * 100),
            'averageMs' => $timed->isEmpty() ? null : (int) round((float) $timed->avg('elapsed_ms')),
            'longest' => $valid->sortByDesc(fn (RunWord $word) => mb_strlen($word->display()))->first(),
            'fastest' => $timed->sortBy('elapsed_ms')->first(),
            'plates' => $this->plates($run, $attempts, $valid->pluck('key')->all()),
        ];
    }

    /**
     * The run plate by plate: a plate ends when a word is accepted or the plate is skipped.
     * Runs recorded before run_words had no plates, so they produce no entries.
     *
     * @param  Collection<int, RunWord>  $attempts
     * @param  list<string|null>  $usedKeys
     * @return list<array{plate: string, digits: string, attempts: list<RunWord>, skipped: bool, examples: list<string>}>
     */
    private function plates(Run $run, Collection $attempts, array $usedKeys): array
    {
        $plates = [];
        $open = false;

        foreach ($attempts as $attempt) {
            if ($attempt->plate === null) {
                continue;
            }

            if (! $open || $plates[array_key_last($plates)]['plate'] !== $attempt->plate) {
                $plates[] = ['plate' => $attempt->plate, 'attempts' => [], 'skipped' => false];
            }

            $current = array_key_last($plates);
            if ($attempt->kind === RunWord::KIND_SKIP) {
                $plates[$current]['skipped'] = true;
            } else {
                $plates[$current]['attempts'][] = $attempt;
            }

            $open = ! $attempt->valid && $attempt->kind !== RunWord::KIND_SKIP;
        }

        $examples = DB::table('plates')
            ->whereIn('plate', array_column($plates, 'plate'))
            ->pluck($run->ordered ? 'examples_ordered' : 'examples', 'plate')
            ->map(fn (?string $json) => json_decode($json ?? '[]', true) ?: []);

        $unused = fn (string $plate) => array_slice(array_values(array_diff($examples[$plate] ?? [], $usedKeys)), 0, self::EXAMPLES_SHOWN);
        $keys = array_merge([], ...array_map(fn (array $plate) => $unused($plate['plate']), $plates));
        $forms = DB::table('words')->whereIn('key', $keys)->whereColumn('form', 'lemma')->pluck('form', 'key');

        return array_map(fn (array $plate, int $index) => $plate + [
            // Decorative plate numbers, stable for the same run.
            'digits' => str_pad((string) (crc32($run->id.'-'.$index) % 10000), 4, '0', STR_PAD_LEFT),
            'examples' => array_map(fn (string $key) => $forms[$key] ?? mb_strtolower($key), $unused($plate['plate'])),
        ], $plates, array_keys($plates));
    }
}
