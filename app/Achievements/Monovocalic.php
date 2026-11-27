<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;

/** A word whose only vowel appears at least four times, like "abracadabra". */
class Monovocalic implements WordAchievement
{
    private const MIN_VOWELS = 4;

    public function isUnlockedByWord(WordPlay $play): bool
    {
        if (! $play->accepted()) {
            return false;
        }

        $vowels = array_values(array_filter(mb_str_split($play->key()), fn (string $char) => str_contains('AEIOU', $char)));

        return count($vowels) >= self::MIN_VOWELS && count(array_unique($vowels)) === 1;
    }
}
