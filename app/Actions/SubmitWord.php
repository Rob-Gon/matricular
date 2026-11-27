<?php

namespace App\Actions;

use App\Enums\GameDifficulties;
use App\Models\Run;
use App\Services\PlateGenerator;
use App\Services\RaeDictionary;
use Illuminate\Support\Facades\DB;

class SubmitWord
{
    public function __construct(
        private PlateGenerator $plates,
        private RaeDictionary $dictionary,
    ) {}

    /**
     * Validates the word against the run's current plate and updates the run.
     *
     * @return array{success: bool, message: string, game_over?: bool}
     */
    public function handle(Run $run, string $input): array
    {
        return DB::transaction(function () use ($run, $input): array {
            // Lock the row so two quick submissions cannot both score or both read the old plate.
            Run::whereKey($run->getKey())->lockForUpdate()->first();
            $run->refresh();

            if ($run->isOver()) {
                return ['success' => false, 'message' => 'La partida ha terminado.', 'game_over' => true];
            }

            $word = mb_strtoupper(trim($input));
            $plate = $run->plateLetters();
            $penalty = 'Has perdido '.Run::POINTS_PENALTY.' puntos.';

            if ($run->hasCorrectWord($word)) {
                return ['success' => false, 'message' => 'Ya has usado esa palabra en esta partida. No suma puntos.'];
            }

            if (! $this->containsLetters($word, $plate)) {
                return $this->reject($run, $word, "¡Fallo! La palabra no contiene todas las letras de la matrícula. {$penalty}");
            }

            if ($run->difficulty === GameDifficulties::DIFICIL->value && ! $this->containsInOrder($word, $plate)) {
                return $this->reject($run, $word, "¡Fallo! En modo difícil, las letras deben aparecer en el mismo orden. {$penalty}");
            }

            $exists = $this->dictionary->exists($word);

            if ($exists === null) {
                return ['success' => false, 'message' => 'No se ha podido verificar la palabra. Inténtalo de nuevo, no has perdido puntos.'];
            }

            if (! $exists) {
                return $this->reject($run, $word, "¡Fallo! La palabra no existe en el diccionario. {$penalty}");
            }

            $run->addCorrectWord($word);
            $run->plate = $this->plates->generate(GameDifficulties::from($run->difficulty));
            $run->save();

            return ['success' => true, 'message' => '¡Palabra válida! Has ganado '.Run::POINTS_CORRECT.' puntos.'];
        });
    }

    /**
     * @return array{success: false, message: string}
     */
    private function reject(Run $run, string $word, string $message): array
    {
        $run->addErrorWord($word);
        $run->save();

        return ['success' => false, 'message' => $message];
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
