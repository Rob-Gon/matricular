<?php

namespace App\Http\Controllers;

use App\Actions\SubmitWord;
use App\Enums\CountdownModes;
use App\Enums\GameDifficulties;
use App\Http\Requests\StartGameRequest;
use App\Http\Requests\SubmitWordRequest;
use App\Models\Run;
use App\Services\PlateGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class GameController extends Controller
{
    /**
     * Crea la run y redirige a su página, para que recargar no reenvíe el formulario.
     */
    public function start(StartGameRequest $request, PlateGenerator $plates): RedirectResponse
    {
        $countdown = $request->countdown();

        $run = Run::create([
            'user_id' => $request->user()->id,
            'score' => 0,
            'mode' => $request->mode()->value,
            'difficulty' => $request->difficulty()->value,
            'countdown_time' => $countdown?->value,
            'plate' => $plates->generate($request->difficulty()),
            'ends_at' => $countdown ? now()->addSeconds($countdown->seconds()) : null,
            'words_correct' => [],
            'words_error' => [],
        ]);

        return redirect()->route('game.show', $run);
    }

    /**
     * Muestra la partida en curso
     */
    public function show(Run $run): View|RedirectResponse
    {
        Gate::authorize('play', $run);

        if ($run->isOver()) {
            return redirect()->route('menu.view')->with('error', 'La partida ha terminado.');
        }

        return view('main.game', [
            'run' => $run,
            'mode' => $run->mode,
            'countdownTotal' => $run->countdown_time ? CountdownModes::from($run->countdown_time)->seconds() : null,
            'countdownRemaining' => $run->remainingSeconds(),
        ]);
    }

    /**
     * Devuelve la vista de modos de cuenta atrás para AJAX
     */
    public function countdownModesView(): View
    {
        return view('components.countdown-modes');
    }

    /**
     * Devuelve la vista de dificultades para AJAX
     */
    public function difficultyModesView(): View
    {
        return view('components.difficulty-modes');
    }

    /**
     * Comprueba la palabra y actualiza la puntuación
     */
    public function checkWord(SubmitWordRequest $request, Run $run, SubmitWord $submitWord): JsonResponse
    {
        Gate::authorize('play', $run);

        $result = $submitWord->handle($run, $request->validated('word'));

        return $this->gameResponse($run, $result, isset($result['game_over']) ? 409 : 200);
    }

    /**
     * Cambia la matrícula con penalización
     */
    public function changePlate(Run $run, PlateGenerator $plates): JsonResponse
    {
        Gate::authorize('play', $run);

        return $run->withLock(function () use ($run, $plates): JsonResponse {
            if ($run->isOver()) {
                return $this->gameResponse($run, ['success' => false, 'message' => 'La partida ha terminado.', 'game_over' => true], 409);
            }

            $run->skipPlate($plates->generate(GameDifficulties::from($run->difficulty)));
            $run->save();

            return $this->gameResponse($run, [
                'success' => false,
                'message' => '¡Imposible! La matrícula ha cambiado. Has perdido '.Run::POINTS_PENALTY.' puntos.',
            ]);
        });
    }

    /**
     * Termina la partida (botón "Terminar partida" o fin de la cuenta atrás)
     */
    public function end(Run $run): JsonResponse
    {
        Gate::authorize('play', $run);

        $run->withLock(function () use ($run): void {
            $run->ended_at ??= now();
            $run->save();
        });

        return $this->gameResponse($run, ['success' => true, 'message' => 'Partida terminada.', 'game_over' => true]);
    }

    /**
     * @param  array{success: bool, message: string, game_over?: bool}  $result
     */
    private function gameResponse(Run $run, array $result, int $status = 200): JsonResponse
    {
        return response()->json([
            ...$result,
            'chars' => $run->plateLetters(),
            'score' => $run->score,
        ], $status);
    }
}
