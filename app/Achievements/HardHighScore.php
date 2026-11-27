<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;
use App\Enums\GameDifficulties;
use App\Models\Run;

class HardHighScore implements WordAchievement
{
    private const SCORE = 250;

    public function isUnlockedByWord(Run $run, string $word): bool
    {
        return $run->difficulty === GameDifficulties::DIFICIL->value && $run->score >= self::SCORE;
    }
}
