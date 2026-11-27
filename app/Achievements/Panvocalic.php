<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;

/** A word with all five vowels, like "murciélago". */
class Panvocalic implements WordAchievement
{
    public function isUnlockedByWord(WordPlay $play): bool
    {
        return $play->accepted() && count(array_intersect(['A', 'E', 'I', 'O', 'U'], mb_str_split($play->key()))) === 5;
    }
}
