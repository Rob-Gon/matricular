<?php

namespace App\Actions;

use App\Models\Run;
use App\Models\RunWord;
use App\Services\Dictionary;
use App\Services\PlateGenerator;
use App\Support\WordNormalizer;

class SubmitWord
{
    /**
     * Famous "longest words" refused on purpose, even if a dictionary has them, and never learned.
     * Trying one unlocks a hidden achievement.
     */
    public const TRAP_WORDS = ['PNEUMONOULTRAMICROSCOPICSILICOVOLCANOCONIOSIS', 'ELECTROENCEFALOGRAFISTA'];

    public function __construct(
        private PlateGenerator $plates,
        private Dictionary $dictionary,
    ) {}

    /**
     * Validates the word against the run's current plate and updates the run.
     * `attempt` holds the recorded attempt, for the achievements; it is not sent to the client.
     *
     * @return array{success: bool, message: string, game_over?: bool, attempt?: RunWord}
     */
    public function handle(Run $run, string $input): array
    {
        // Locked so two quick submissions cannot both score or both read the old plate.
        return $run->withLock(function () use ($run, $input): array {
            if ($run->isOver()) {
                return ['success' => false, 'message' => 'La partida ha terminado.', 'game_over' => true];
            }

            // Stored and compared by key, so "camión" and "camion" are the same word.
            $input = trim($input);
            $word = WordNormalizer::key($input);
            $plate = $run->plateLetters();
            $penalty = 'Has perdido '.Run::POINTS_PENALTY.' puntos.';

            if ($run->hasCorrectWord($word)) {
                $attempt = $run->addRepeatedWord($word, $input);

                return ['success' => false, 'message' => 'Ya has usado esa palabra en esta partida. No suma puntos.', 'attempt' => $attempt];
            }

            if (in_array($word, self::TRAP_WORDS, true)) {
                return $this->reject($run, $word, $input, RunWord::REASON_TRAP, "¡Buen intento! Esa palabra no cuela. {$penalty}");
            }

            if (! $this->containsLetters($word, $plate)) {
                return $this->reject($run, $word, $input, RunWord::REASON_MISSING_LETTERS, "¡Fallo! La palabra no contiene todas las letras de la matrícula. {$penalty}");
            }

            if ($run->ordered && ! $this->containsInOrder($word, $plate)) {
                return $this->reject($run, $word, $input, RunWord::REASON_WRONG_ORDER, "¡Fallo! Las letras deben aparecer en el orden de la matrícula. {$penalty}");
            }

            // The raw input keeps any accents the player typed, which saves the API a suggestion round.
            $form = $this->dictionary->find($input);

            if ($form === null) {
                return ['success' => false, 'message' => 'No se ha podido verificar la palabra. Inténtalo de nuevo, no has perdido puntos.'];
            }

            if ($form === false) {
                return $this->reject($run, $word, $input, RunWord::REASON_NOT_IN_DICTIONARY, "¡Fallo! La palabra no existe en el diccionario. {$penalty}");
            }

            $attempt = $run->addCorrectWord($word, $input, $form, $this->dictionary->categories($word));
            $run->dealPlate($this->plates->generate($run->letter_set, $run->ordered));
            $run->save();

            return ['success' => true, 'message' => '¡Palabra válida! Has ganado '.Run::POINTS_CORRECT.' puntos.', 'attempt' => $attempt];
        });
    }

    /**
     * @return array{success: false, message: string, attempt: RunWord}
     */
    private function reject(Run $run, string $word, string $input, string $reason, string $message): array
    {
        $attempt = $run->addErrorWord($word, $input, $reason);
        $run->save();

        return ['success' => false, 'message' => $message, 'attempt' => $attempt];
    }

    /**
     * Every plate letter must appear, counting repeats (plate SSL needs two S).
     * Plate letters are ASCII, so byte counting is safe on UTF-8 words.
     *
     * @param  list<string>  $plate
     */
    private function containsLetters(string $word, array $plate): bool
    {
        foreach (array_count_values($plate) as $letter => $count) {
            if (substr_count($word, (string) $letter) < $count) {
                return false;
            }
        }

        return true;
    }

    /**
     * The plate letters must appear as a subsequence of the word, in plate order.
     *
     * @param  list<string>  $plate
     */
    private function containsInOrder(string $word, array $plate): bool
    {
        $next = 0;

        foreach (mb_str_split($word) as $char) {
            if ($char === $plate[$next] && ++$next === count($plate)) {
                return true;
            }
        }

        return false;
    }
}
