<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;
use App\Models\RunWord;

/** Famous "longest words" that are refused on purpose; trying one unlocks a hidden achievement. */
abstract class TrapWord implements WordAchievement
{
    abstract protected function word(): string;

    public function isUnlockedByWord(WordPlay $play): bool
    {
        return $play->attempt->reason === RunWord::REASON_TRAP && $play->key() === $this->word();
    }
}
