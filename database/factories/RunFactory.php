<?php

namespace Database\Factories;

use App\Enums\CountdownModes;
use App\Enums\GameModes;
use App\Enums\LetterSets;
use App\Models\Run;
use App\Models\RunWord;
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
        ];
    }

    /**
     * Creates the run's attempts: accepted words, rejected words and skipped plates (keys, uppercase).
     *
     * @param  list<string>  $valid
     * @param  list<string>  $invalid
     */
    public function attempts(array $valid = [], array $invalid = [], int $skips = 0): static
    {
        return $this->afterCreating(function (Run $run) use ($valid, $invalid, $skips): void {
            foreach ($valid as $key) {
                $run->runWords()->create(['kind' => RunWord::KIND_WORD, 'plate' => $run->plate, 'input' => mb_strtolower($key), 'key' => $key, 'valid' => true, 'points' => Run::POINTS_CORRECT]);
            }
            foreach ($invalid as $key) {
                $run->runWords()->create(['kind' => RunWord::KIND_WORD, 'plate' => $run->plate, 'input' => mb_strtolower($key), 'key' => $key, 'valid' => false, 'points' => -Run::POINTS_PENALTY]);
            }
            for ($i = 0; $i < $skips; $i++) {
                $run->runWords()->create(['kind' => RunWord::KIND_SKIP, 'plate' => $run->plate, 'points' => -Run::POINTS_PENALTY]);
            }
        });
    }

    public function countdown(CountdownModes $countdown = CountdownModes::UN_MINUTO): static
    {
        return $this->state(fn () => [
            'mode' => GameModes::CONTRARELOJ->value,
            'countdown_time' => $countdown->value,
            'countdown_seconds' => $countdown->seconds(),
            'ends_at' => now()->addSeconds((int) $countdown->seconds()),
        ]);
    }

    public function customCountdown(int $minutes): static
    {
        return $this->state(fn () => [
            'mode' => GameModes::CONTRARELOJ->value,
            'countdown_time' => CountdownModes::PERSONALIZADO->value,
            'countdown_seconds' => $minutes * 60,
            'ends_at' => now()->addMinutes($minutes),
        ]);
    }

    public function settings(LetterSets $letterSet, bool $ordered = false): static
    {
        return $this->state(fn () => ['letter_set' => $letterSet, 'ordered' => $ordered]);
    }
}
