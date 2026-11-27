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
    /** The player picks 1 to MAX_CUSTOM_MINUTES minutes; stored in runs.countdown_seconds. */
    case PERSONALIZADO = 'Personalizado';

    public const MAX_CUSTOM_MINUTES = 60;

    public function value(): string
    {
        return $this->value;
    }

    public function isCustom(): bool
    {
        return $this === self::PERSONALIZADO;
    }

    public function shortLabel(): string
    {
        $seconds = $this->seconds();

        if ($seconds === null) {
            return 'Personalizado';
        }

        return $seconds < 60 ? "{$seconds} s" : ($seconds / 60).' min';
    }

    /** Fixed durations only; a custom countdown has no preset length. */
    public function seconds(): ?int
    {
        return match ($this) {
            self::TREINTA_SEGUNDOS => 30,
            self::UN_MINUTO => 60,
            self::DOS_MINUTOS => 120,
            self::TRES_MINUTOS => 180,
            self::CUATRO_MINUTOS => 240,
            self::CINCO_MINUTOS => 300,
            self::PERSONALIZADO => null,
        };
    }
}
