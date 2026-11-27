<?php

namespace App\Achievements;

use App\Achievements\Contracts\RunAchievement;
use App\Models\Run;

class NegativeScore implements RunAchievement
{
    public function isUnlockedByRun(Run $run): bool
    {
        return $run->score < 0;
    }
}
