<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;
use App\Models\Run;

class HighScore implements WordAchievement
{
    private const SCORE = 500;

    public function isUnlockedByWord(Run $run, string $word): bool
    {
        return $run->score >= self::SCORE;
    }
}
