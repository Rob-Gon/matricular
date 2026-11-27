<?php

namespace App\Enums;

use App\Services\PlateGenerator;

enum LetterSets: string
{
    case SIMPLE = 'simple';
    case FULL = 'full';

    /**
     * @return list<string>
     */
    public function letters(): array
    {
        return $this === self::SIMPLE ? PlateGenerator::SIMPLE_LETTERS : PlateGenerator::FULL_LETTERS;
    }
}
