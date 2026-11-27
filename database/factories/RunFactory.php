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
            'words_correct' => [],
            'words_error' => [],
        ];
    }

    /**
     * Mirrors the JSON arrays given to the factory into run_words, as the backfill did for real runs.
     * shortcut: goes away with the JSON columns (F4, next PR); tests will then create run_words directly.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Run $run): void {
            foreach ($run->words_correct ?? [] as $key) {
                $run->runWords()->create(['kind' => RunWord::KIND_WORD, 'plate' => $run->plate, 'input' => mb_strtolower($key), 'key' => $key, 'valid' => true, 'points' => Run::POINTS_CORRECT]);
            }
            foreach ($run->words_error ?? [] as $key) {
                $run->runWords()->create(['kind' => RunWord::KIND_WORD, 'plate' => $run->plate, 'input' => mb_strtolower($key), 'key' => $key, 'valid' => false, 'points' => -Run::POINTS_PENALTY]);
            }
            foreach ($run->chars_skipped ?? [] as $letters) {
                $run->runWords()->create(['kind' => RunWord::KIND_SKIP, 'plate' => implode('', $letters), 'points' => -Run::POINTS_PENALTY]);
            }
        });
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
