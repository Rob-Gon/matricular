<?php

namespace App\Enums;

/** Multiplayer formats (see .claude/rules/game.md). */
enum LobbyFormats: string
{
    case RACE = 'race';
    case ROUNDS = 'rounds';
    case FIRST = 'first';

    public function description(): string
    {
        return match ($this) {
            self::RACE => 'Cada uno a su ritmo con las mismas matrículas',
            self::ROUNDS => 'La misma matrícula para todos en cada ronda',
            self::FIRST => 'Quien acierta primero se lleva la matrícula',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::RACE => 'Carrera',
            self::ROUNDS => 'Rondas',
            self::FIRST => 'El primero gana',
        };
    }

    /**
     * Settings a new lobby starts with for this format.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $common = ['letter_set' => LetterSets::SIMPLE->value, 'ordered' => false, 'tie_resolution' => 'tiebreak'];

        return $common + match ($this) {
            self::RACE => ['countdown_time' => CountdownModes::DOS_MINUTOS->value, 'countdown_seconds' => 120],
            self::ROUNDS => ['rounds' => 10, 'round_seconds' => 20],
            self::FIRST => ['plates' => 15, 'plate_seconds' => 30, 'tie_window_ms' => 150],
        };
    }
}
