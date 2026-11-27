<?php

namespace App\Achievements\Contracts;

use App\Models\Run;

/** Checked after every accepted word. Keep it to in-memory checks on the run and the word. */
interface WordAchievement
{
    public function isUnlockedByWord(Run $run, string $word): bool;
}
