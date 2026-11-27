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

            if (! $run->hasStarted()) {
                return ['success' => false, 'message' => 'Espera al semáforo verde.'];
            }

            // Stored and compared by key, so "camión" and "camion" are the same word.
            $input = trim($input);
            $word = WordNormalizer::key($input);

            if ($run->hasCorrectWord($word)) {
                $attempt = $run->addRepeatedWord($word, $input);

                return ['success' => false, 'message' => 'Ya has usado esa palabra en esta partida. No suma puntos.', 'attempt' => $attempt];
            }

            $verdict = $this->judge($input, $run->plateLetters(), $run->ordered);

            if ($verdict === null) {
                return ['success' => false, 'message' => 'No se ha podido verificar la palabra. Inténtalo de nuevo, no has perdido puntos.'];
            }

            if ($verdict['reason'] !== null) {
                $attempt = $run->addErrorWord($word, $input, $verdict['reason']);
                $run->save();

                return [
                    'success' => false,
                    'message' => self::failure($verdict['reason']).' Has perdido '.Run::POINTS_PENALTY.' puntos.',
                    'attempt' => $attempt,
                ];
            }

            $form = $verdict['form'];
            $attempt = $run->addCorrectWord($word, $input, $form, $this->dictionary->categories($word));
            $run->dealPlate($run->nextPlate($this->plates));
            $run->save();

            return ['success' => true, 'message' => '¡Palabra válida! Has ganado '.Run::POINTS_CORRECT.' puntos.', 'attempt' => $attempt];
        });
    }

    /**
     * Whether a word solves a plate: a refusal reason (RunWord::REASON_*) or the dictionary spelling.
     * Null when the dictionary could not be reached, so the player loses nothing.
     *
     * @param  list<string>  $plate
     * @return array{reason: string|null, form: string|null}|null
     */
    public function judge(string $input, array $plate, bool $ordered): ?array
    {
        $input = trim($input);
        $word = WordNormalizer::key($input);

        $reason = match (true) {
            in_array($word, self::TRAP_WORDS, true) => RunWord::REASON_TRAP,
            ! $this->containsLetters($word, $plate) => RunWord::REASON_MISSING_LETTERS,
            $ordered && ! $this->containsInOrder($word, $plate) => RunWord::REASON_WRONG_ORDER,
            default => null,
        };

        if ($reason !== null) {
            return ['reason' => $reason, 'form' => null];
        }

        // The raw input keeps any accents the player typed, which saves the API a suggestion round.
        $form = $this->dictionary->find($input);

        return match ($form) {
            null => null,
            false => ['reason' => RunWord::REASON_NOT_IN_DICTIONARY, 'form' => null],
            default => ['reason' => null, 'form' => $form],
        };
    }

    /** What the player reads when a word is refused (without the penalty). */
    public static function failure(string $reason): string
    {
        return match ($reason) {
            RunWord::REASON_TRAP => '¡Buen intento! Esa palabra no cuela.',
            RunWord::REASON_MISSING_LETTERS => '¡Fallo! La palabra no contiene todas las letras de la matrícula.',
            RunWord::REASON_WRONG_ORDER => '¡Fallo! Las letras deben aparecer en el orden de la matrícula.',
            default => '¡Fallo! La palabra no existe en el diccionario.',
        };
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
