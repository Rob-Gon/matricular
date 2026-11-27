<?php

namespace App\Achievements;

use App\Achievements\Contracts\RunAchievement;
use App\Models\Run;

class FirstGame implements RunAchievement
{
    public function isUnlockedByRun(Run $run): bool
    {
        return $run->words_correct !== [] || $run->words_error !== [];
    }
}
