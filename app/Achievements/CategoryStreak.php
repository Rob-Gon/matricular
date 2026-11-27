<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;
use App\Models\RunWord;

/** Ten accepted words of one part of speech in a single run ("Sustantívame esta" and friends). */
abstract class CategoryStreak implements WordAchievement
{
    private const COUNT = 10;

    abstract protected function category(): string;

    public function isUnlockedByWord(WordPlay $play): bool
    {
        if (! $play->accepted() || ! $play->attempt->hasCategory($this->category())) {
            return false;
        }

        return $play->history()
            ->filter(fn (RunWord $word) => $word->valid && $word->hasCategory($this->category()))
            ->count() >= self::COUNT;
    }
}
