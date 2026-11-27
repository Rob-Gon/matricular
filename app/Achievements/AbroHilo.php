<?php

namespace App\Achievements;

use App\Achievements\Contracts\WordAchievement;
use App\Models\RunWord;

/** A noun, an adjective and a verb accepted one after another, in any order. */
class AbroHilo implements WordAchievement
{
    private const CATEGORIES = ['noun', 'adj', 'verb'];

    public function isUnlockedByWord(WordPlay $play): bool
    {
        if (! $play->accepted() || ! array_intersect(self::CATEGORIES, $play->attempt->categoryList())) {
            return false;
        }

        $last = $play->history()->slice(-3)->values();

        if ($last->count() < 3 || $last->contains(fn (RunWord $word) => ! $word->valid)) {
            return false;
        }

        // Each word must cover a different category; a word may have several to choose from.
        foreach ([[0, 1, 2], [0, 2, 1], [1, 0, 2], [1, 2, 0], [2, 0, 1], [2, 1, 0]] as $order) {
            if ($last[0]->hasCategory(self::CATEGORIES[$order[0]])
                && $last[1]->hasCategory(self::CATEGORIES[$order[1]])
                && $last[2]->hasCategory(self::CATEGORIES[$order[2]])) {
                return true;
            }
        }

        return false;
    }
}
