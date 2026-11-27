<?php

namespace App\Enums;

enum CountdownModes: string
{
    case TREINTA_SEGUNDOS = '30 segundos';
    case UN_MINUTO = '1 minuto';
    case DOS_MINUTOS = '2 minutos';
    case TRES_MINUTOS = '3 minutos';
    case CUATRO_MINUTOS = '4 minutos';
    case CINCO_MINUTOS = '5 minutos';

    public function value(): string
    {
        return $this->value;
    }

    public function seconds(): int
    {
        return match ($this) {
            self::TREINTA_SEGUNDOS => 30,
            self::UN_MINUTO => 60,
            self::DOS_MINUTOS => 120,
            self::TRES_MINUTOS => 180,
            self::CUATRO_MINUTOS => 240,
            self::CINCO_MINUTOS => 300,
        };
    }
}
