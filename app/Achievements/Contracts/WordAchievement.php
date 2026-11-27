<?php

namespace App\Achievements\Contracts;

use App\Achievements\WordPlay;

/**
 * Checked after every word attempt, accepted or not. Keep checks in memory; WordPlay::history()
 * loads the run's attempts once for the checks that need them.
 */
interface WordAchievement
{
    public function isUnlockedByWord(WordPlay $play): bool;
}
