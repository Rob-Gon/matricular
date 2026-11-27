<?php

namespace App\Achievements;

use App\Actions\SubmitWord;

class TrapPneumono extends TrapWord
{
    protected function word(): string
    {
        return SubmitWord::TRAP_WORDS[0];
    }
}
