<?php

namespace App\Console\Commands;

use App\Enums\LetterSets;
use App\Services\PlateGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BuildPlates extends Command
{
    /** Shortest words kept per plate; the trip receipt shows a few of them, minus the ones already played. */
    public const EXAMPLES = 6;

    protected $signature = 'dictionary:plates';

    protected $description = 'Count how many dictionary words solve each possible plate and keep the shortest ones';

    public function handle(): int
    {
        $started = microtime(true);
        $alphabet = array_flip(PlateGenerator::FULL_LETTERS);

        /** @var array<string, int> $ordered words containing the plate letters in plate order */
        $ordered = [];
        /** @var array<string, int> $anyOrder words containing the letters in any order, keyed by sorted letters */
        $anyOrder = [];
        /** @var array<string, array<string, int>> $orderedExamples shortest keys per plate => length */
        $orderedExamples = [];
        /** @var array<string, array<string, int>> $anyOrderExamples shortest keys per sorted letters => length */
        $anyOrderExamples = [];

        // Examples must look like real everyday words: a headword (singular, infinitive) that Wiktionary
        // also lists. That drops conjugations like "ahajad", abbreviations and rare spellings.
        $exampleKeys = array_flip(DB::table('words')
            ->whereColumn('form', 'lemma')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))->from('word_categories')->whereColumn('word_categories.word', 'words.lemma'))
            ->distinct()
            ->pluck('key')
            ->all());

        // Keys are uppercase without accents, so a word's plate letters are plain ASCII bytes.
        // Keys arrive in alphabetical order, so among words of equal length the first ones are kept.
        foreach (DB::table('words')->select('key')->distinct()->lazyById(5000, 'key') as $row) {
            $letters = array_values(array_filter(str_split($row->key), fn (string $char) => isset($alphabet[$char])));
            $count = count($letters);

            if ($count < 3) {
                continue;
            }

            $triples = [];
            for ($i = 0; $i < $count - 2; $i++) {
                for ($j = $i + 1; $j < $count - 1; $j++) {
                    for ($k = $j + 1; $k < $count; $k++) {
                        $triples[$letters[$i].$letters[$j].$letters[$k]] = true;
                    }
                }
            }

            $length = isset($exampleKeys[$row->key]) ? mb_strlen($row->key) : null;
            $sorted = [];
            foreach (array_keys($triples) as $triple) {
                $ordered[$triple] = ($ordered[$triple] ?? 0) + 1;
                $sorted[$this->sortLetters((string) $triple)] = true;

                if ($length !== null) {
                    $this->keepShortest($orderedExamples, (string) $triple, $row->key, $length);
                }
            }

            foreach (array_keys($sorted) as $multiset) {
                $anyOrder[$multiset] = ($anyOrder[$multiset] ?? 0) + 1;

                if ($length !== null) {
                    $this->keepShortest($anyOrderExamples, (string) $multiset, $row->key, $length);
                }
            }
        }

        $simple = array_flip(PlateGenerator::SIMPLE_LETTERS);
        $rows = [];

        foreach (PlateGenerator::FULL_LETTERS as $a) {
            foreach (PlateGenerator::FULL_LETTERS as $b) {
                foreach (PlateGenerator::FULL_LETTERS as $c) {
                    $plate = $a.$b.$c;
                    $multiset = $this->sortLetters($plate);
                    $rows[] = [
                        'plate' => $plate,
                        'simple' => isset($simple[$a], $simple[$b], $simple[$c]),
                        'solutions' => $anyOrder[$multiset] ?? 0,
                        'solutions_ordered' => $ordered[$plate] ?? 0,
                        'examples' => json_encode(array_keys($anyOrderExamples[$multiset] ?? [])),
                        'examples_ordered' => json_encode(array_keys($orderedExamples[$plate] ?? [])),
                    ];
                }
            }
        }

        DB::transaction(function () use ($rows): void {
            DB::table('plates')->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('plates')->insert($chunk);
            }
        });

        foreach (LetterSets::cases() as $letterSet) {
            Cache::forget(PlateGenerator::cacheKey($letterSet, false));
            Cache::forget(PlateGenerator::cacheKey($letterSet, true));
        }

        $playable = collect($rows)->where('solutions', '>=', PlateGenerator::MIN_SOLUTIONS)->count();
        $this->info(sprintf(
            'Built %d plates (%d with at least %d solutions) in %.1f s.',
            count($rows), $playable, PlateGenerator::MIN_SOLUTIONS, microtime(true) - $started,
        ));

        return self::SUCCESS;
    }

    /**
     * Keeps the EXAMPLES shortest keys for a plate, sorted by length (stable, so ties stay alphabetical).
     *
     * @param  array<string, array<string, int>>  $examples
     */
    private function keepShortest(array &$examples, string $plate, string $key, int $length): void
    {
        $top = $examples[$plate] ?? [];

        if (count($top) >= self::EXAMPLES && $length >= end($top)) {
            return;
        }

        $top[$key] = $length;
        asort($top);
        $examples[$plate] = array_slice($top, 0, self::EXAMPLES, true);
    }

    private function sortLetters(string $plate): string
    {
        $letters = str_split($plate);
        sort($letters);

        return implode('', $letters);
    }
}
