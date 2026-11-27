<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;
use App\Models\Run;

class Palindrome implements WordAchievement
{
    public function isUnlockedByWord(Run $run, string $word): bool
    {
        // Accepted words are normalized keys (no accents), so "reconocer" compares letter by letter.
        return implode('', array_reverse(mb_str_split($word))) === $word;
    }
}
