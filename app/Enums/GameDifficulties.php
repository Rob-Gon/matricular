<?php

namespace App\Enums;

enum GameDifficulties: string
{
    case FACIL = 'Facil';
    case MEDIO = 'Medio';
    case DIFICIL = 'Dificil';

    public function value(): string
    {
        return $this->value;
    }
}
