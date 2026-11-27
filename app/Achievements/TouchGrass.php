<?php

namespace App\Achievements;

use App\Achievements\Contracts\RunAchievement;
use App\Models\Run;

class TouchGrass implements RunAchievement
{
    private const RUNS = 25;

    public function isUnlockedByRun(Run $run): bool
    {
        // shortcut: "a day" is the calendar day in the app timezone (UTC); use the player's timezone once profiles store it.
        return Run::attempted()
            ->where('user_id', $run->user_id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count() >= self::RUNS;
    }
}
