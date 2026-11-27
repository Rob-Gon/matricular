<?php

namespace App\Achievements;

use App\Actions\SubmitWord;

class TrapElectroencefalografista extends TrapWord
{
    protected function word(): string
    {
        return SubmitWord::TRAP_WORDS[1];
    }
}
