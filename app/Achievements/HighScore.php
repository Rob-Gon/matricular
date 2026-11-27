<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;

class HighScore implements WordAchievement
{
    private const SCORE = 500;

    public function isUnlockedByWord(WordPlay $play): bool
    {
        // Only preset countdowns: infinite and custom runs reach any score given time.
        return $play->run->countdown_time !== null && ! $play->run->isCustomCountdown() && $play->run->score >= self::SCORE;
    }
}
