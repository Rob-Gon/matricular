<?php

namespace App\Achievements;

use App\Achievements\Contracts\RunAchievement;
use App\Models\Run;

class QuickSuccession implements RunAchievement
{
    private const RUNS = 5;

    private const MINUTES = 10;

    public function isUnlockedByRun(Run $run): bool
    {
        // Runs started within the window, including the one that just ended.
        return Run::attempted()
            ->where('user_id', $run->user_id)
            ->where('created_at', '>=', now()->subMinutes(self::MINUTES))
            ->count() >= self::RUNS;
    }
}
