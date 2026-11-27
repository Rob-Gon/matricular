<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;

class Palindrome implements WordAchievement
{
    public function isUnlockedByWord(WordPlay $play): bool
    {
        // Accepted words are normalized keys (no accents), so "reconocer" compares letter by letter.
        return $play->accepted() && implode('', array_reverse(mb_str_split($play->key()))) === $play->key();
    }
}
