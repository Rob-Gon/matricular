<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;
use App\Models\Run;

class LongWord implements WordAchievement
{
    private const MIN_LENGTH = 12;

    public function isUnlockedByWord(Run $run, string $word): bool
    {
        return mb_strlen($word) >= self::MIN_LENGTH;
    }
}
