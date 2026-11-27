<?php

namespace App\Services;

use App\Enums\GameDifficulties;

class PlateGenerator
{
    /** Spanish plate letters without K, V, W, X, Y, Z, which are hard to use in Spanish words. */
    private const SIMPLE_LETTERS = ['B', 'C', 'D', 'F', 'G', 'H', 'J', 'L', 'M', 'N', 'P', 'R', 'S', 'T'];

    /** Every letter used on Spanish plates: no vowels, no Ñ, no Q. */
    private const FULL_LETTERS = ['B', 'C', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V', 'W', 'X', 'Y', 'Z'];

    public function generate(GameDifficulties $difficulty): string
    {
        $letters = $difficulty === GameDifficulties::FACIL ? self::SIMPLE_LETTERS : self::FULL_LETTERS;

        $plate = '';
        for ($i = 0; $i < 3; $i++) {
            $plate .= $letters[random_int(0, count($letters) - 1)];
        }

        return $plate;
    }
}
