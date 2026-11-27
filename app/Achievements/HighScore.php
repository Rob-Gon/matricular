<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;

class HighScore implements WordAchievement
{
    private const SCORE = 500;

    public function isUnlockedByWord(WordPlay $play): bool
    {
        // Only countdowns: an infinite run reaches any score given time.
        return $play->run->countdown_time !== null && $play->run->score >= self::SCORE;
    }
}
