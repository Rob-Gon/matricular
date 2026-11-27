<?php

namespace App\Achievements;

class NounsInARun extends CategoryStreak
{
    protected function category(): string
    {
        return 'noun';
    }
}
