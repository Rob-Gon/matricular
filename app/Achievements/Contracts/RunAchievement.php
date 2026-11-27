<?php

namespace App\Achievements\Contracts;

use App\Models\Run;

/** Checked once when a run ends. */
interface RunAchievement
{
    public function isUnlockedByRun(Run $run): bool;
}
