<?php

namespace App\Console\Commands;

use App\Enums\GameDifficulties;
use App\Services\PlateGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BuildPlates extends Command
{
    protected $signature = 'dictionary:plates';

    protected $description = 'Count how many dictionary words solve each possible plate';

    public function handle(): int
    {
        $started = microtime(true);
        $alphabet = array_flip(PlateGenerator::FULL_LETTERS);

        /** @var array<string, int> $ordered words containing the plate letters in plate order */
        $ordered = [];
        /** @var array<string, int> $anyOrder words containing the letters in any order, keyed by sorted letters */
        $anyOrder = [];

        // Keys are uppercase without accents, so a word's plate letters are plain ASCII bytes.
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

            $sorted = [];
            foreach (array_keys($triples) as $triple) {
                $ordered[$triple] = ($ordered[$triple] ?? 0) + 1;
                $sorted[$this->sortLetters((string) $triple)] = true;
            }

            foreach (array_keys($sorted) as $multiset) {
                $anyOrder[$multiset] = ($anyOrder[$multiset] ?? 0) + 1;
            }
        }

        $simple = array_flip(PlateGenerator::SIMPLE_LETTERS);
        $rows = [];

        foreach (PlateGenerator::FULL_LETTERS as $a) {
            foreach (PlateGenerator::FULL_LETTERS as $b) {
                foreach (PlateGenerator::FULL_LETTERS as $c) {
                    $plate = $a.$b.$c;
                    $rows[] = [
                        'plate' => $plate,
                        'simple' => isset($simple[$a], $simple[$b], $simple[$c]),
                        'solutions' => $anyOrder[$this->sortLetters($plate)] ?? 0,
                        'solutions_ordered' => $ordered[$plate] ?? 0,
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

        foreach (GameDifficulties::cases() as $difficulty) {
            Cache::forget(PlateGenerator::CACHE_PREFIX.$difficulty->value);
        }

        $playable = collect($rows)->where('solutions', '>=', PlateGenerator::MIN_SOLUTIONS)->count();
        $this->info(sprintf(
            'Built %d plates (%d with at least %d solutions) in %.1f s.',
            count($rows), $playable, PlateGenerator::MIN_SOLUTIONS, microtime(true) - $started,
        ));

        return self::SUCCESS;
    }

    private function sortLetters(string $plate): string
    {
        $letters = str_split($plate);
        sort($letters);

        return implode('', $letters);
    }
}
