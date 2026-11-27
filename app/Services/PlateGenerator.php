<?php

namespace App\Services;

use App\Enums\GameDifficulties;
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

    public function generate(GameDifficulties $difficulty): string
    {
        $eligible = $this->eligible($difficulty);

        // Without the plates table (fresh install, tests) fall back to random letters.
        if ($eligible === []) {
            return $this->randomPlate($difficulty);
        }

        return $eligible[array_rand($eligible)];
    }

    /**
     * @return list<string>
     */
    private function eligible(GameDifficulties $difficulty): array
    {
        return Cache::remember(self::CACHE_PREFIX.$difficulty->value, now()->addDay(), function () use ($difficulty): array {
            $column = $difficulty === GameDifficulties::DIFICIL ? 'solutions_ordered' : 'solutions';

            return DB::table('plates')
                ->where($column, '>=', self::MIN_SOLUTIONS)
                ->when($difficulty === GameDifficulties::FACIL, fn ($query) => $query->where('simple', true))
                ->pluck('plate')
                ->all();
        });
    }

    private function randomPlate(GameDifficulties $difficulty): string
    {
        $letters = $difficulty === GameDifficulties::FACIL ? self::SIMPLE_LETTERS : self::FULL_LETTERS;

        $plate = '';
        for ($i = 0; $i < 3; $i++) {
            $plate .= $letters[random_int(0, count($letters) - 1)];
        }

        return $plate;
    }
}
