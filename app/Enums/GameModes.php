<?php

namespace App\Enums;

enum GameModes: string
{
    case CONTRARELOJ = 'Contrareloj';
    case INFINITO = 'Infinito';

    public function value(): string
    {
        return $this->value;
    }
}
