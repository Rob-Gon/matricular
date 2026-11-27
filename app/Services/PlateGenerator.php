<?php

namespace App\Services;

use App\Enums\LetterSets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlateGenerator
{
    /** Spanish plate letters without K, V, W, X, Y, Z, which are hard to use in Spanish words. */
    public const SIMPLE_LETTERS = ['B', 'C', 'D', 'F', 'G', 'H', 'J', 'L', 'M', 'N', 'P', 'R', 'S', 'T'];

    /** Every letter used on Spanish plates: no vowels, no Ñ, no Q. */
    public const FULL_LETTERS = ['B', 'C', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V', 'W', 'X', 'Y', 'Z'];

    /** A plate is only dealt when at least this many dictionary words solve it under the game's rules. */
    public const MIN_SOLUTIONS = 10;

    public const CACHE_PREFIX = 'plates:eligible:';

    public function generate(LetterSets $letterSet, bool $ordered): string
    {
        $eligible = $this->eligible($letterSet, $ordered);

        // Without the plates table (fresh install, tests) fall back to random letters.
        if ($eligible === []) {
            return $this->randomPlate($letterSet);
        }

        return $eligible[array_rand($eligible)];
    }

    public static function cacheKey(LetterSets $letterSet, bool $ordered): string
    {
        return self::CACHE_PREFIX.$letterSet->value.($ordered ? ':ordered' : ':any');
    }

    /**
     * @return list<string>
     */
    private function eligible(LetterSets $letterSet, bool $ordered): array
    {
        return Cache::remember(self::cacheKey($letterSet, $ordered), now()->addDay(), function () use ($letterSet, $ordered): array {
            return DB::table('plates')
                ->where($ordered ? 'solutions_ordered' : 'solutions', '>=', self::MIN_SOLUTIONS)
                ->when($letterSet === LetterSets::SIMPLE, fn ($query) => $query->where('simple', true))
                ->pluck('plate')
                ->all();
        });
    }

    private function randomPlate(LetterSets $letterSet): string
    {
        $letters = $letterSet->letters();

        $plate = '';
        for ($i = 0; $i < 3; $i++) {
            $plate .= $letters[random_int(0, count($letters) - 1)];
        }

        return $plate;
    }
}
