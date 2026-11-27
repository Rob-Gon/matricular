<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;

class LongWord implements WordAchievement
{
    private const MIN_LENGTH = 12;

    public function isUnlockedByWord(WordPlay $play): bool
    {
        return $play->accepted() && mb_strlen($play->key()) >= self::MIN_LENGTH;
    }
}
