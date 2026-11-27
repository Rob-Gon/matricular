<?php

namespace Database\Factories;

use App\Enums\CountdownModes;
use App\Enums\GameModes;
use App\Enums\LetterSets;
use App\Models\Run;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'score' => 0,
            'mode' => GameModes::INFINITO->value,
            'letter_set' => LetterSets::SIMPLE,
            'ordered' => false,
            'countdown_time' => null,
            'plate' => 'BRC',
            'ends_at' => null,
            'words_correct' => [],
            'words_error' => [],
        ];
    }

    public function countdown(CountdownModes $countdown = CountdownModes::UN_MINUTO): static
    {
        return $this->state(fn () => [
            'mode' => GameModes::CONTRARELOJ->value,
            'countdown_time' => $countdown->value,
            'ends_at' => now()->addSeconds($countdown->seconds()),
        ]);
    }

    public function settings(LetterSets $letterSet, bool $ordered = false): static
    {
        return $this->state(fn () => ['letter_set' => $letterSet, 'ordered' => $ordered]);
    }
}
