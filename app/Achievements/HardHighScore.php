<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;
use App\Enums\LetterSets;

class HardHighScore implements WordAchievement
{
    private const SCORE = 250;

    public function isUnlockedByWord(WordPlay $play): bool
    {
        return $play->run->letter_set === LetterSets::FULL && $play->run->ordered && $play->run->score >= self::SCORE;
    }
}
