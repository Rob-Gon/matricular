<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;
use App\Enums\LetterSets;
use App\Models\Run;

class HardHighScore implements WordAchievement
{
    private const SCORE = 250;

    public function isUnlockedByWord(Run $run, string $word): bool
    {
        return $run->letter_set === LetterSets::FULL && $run->ordered && $run->score >= self::SCORE;
    }
}
