<?php

namespace App\Http\Controllers;

use App\Achievements\WordPlay;
use App\Actions\SubmitWord;
use App\Enums\GameModes;
use App\Http\Requests\StartGameRequest;
use App\Http\Requests\SubmitWordRequest;
use App\Models\Run;
use App\Services\AchievementUnlocker;
use App\Services\PlateGenerator;
use App\Services\TripReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class GameController extends Controller
{
    /**
     * Crea la run y redirige a su página, para que recargar no reenvíe el formulario.
     */
    public function start(StartGameRequest $request, PlateGenerator $plates): RedirectResponse
    {
        $countdown = $request->countdown();
        $seconds = $request->countdownSeconds();

        $run = Run::create([
            'user_id' => $request->user()->id,
            'score' => 0,
            'mode' => $request->mode()->value,
            'letter_set' => $request->letterSet(),
            'ordered' => $request->ordered(),
            'countdown_time' => $countdown?->value,
            'countdown_seconds' => $seconds,
            'plate' => $plates->generate($request->letterSet(), $request->ordered()),
            'plate_dealt_at' => now(),
            'ends_at' => $seconds ? now()->addSeconds($seconds) : null,
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
            return redirect()->route('game.summary', $run);
        }

        return view('main.game', [
            'run' => $run,
            'countdownTotal' => $run->countdown_seconds,
            'countdownRemaining' => $run->remainingSeconds(),
        ]);
    }

    /**
     * Game settings screen. The menu may preselect a mode with ?mode=.
     */
    public function setup(Request $request): View
    {
        return view('main.setup', [
            'mode' => GameModes::tryFrom((string) $request->query('mode')) ?? GameModes::CONTRARELOJ,
        ]);
    }

    /**
     * Comprueba la palabra y actualiza la puntuación
     */
    public function checkWord(SubmitWordRequest $request, Run $run, SubmitWord $submitWord, AchievementUnlocker $achievements): JsonResponse
    {
        Gate::authorize('play', $run);

        $result = $submitWord->handle($run, $request->validated('word'));
        $attempt = Arr::pull($result, 'attempt');

        if ($attempt !== null) {
            $achievements->afterWord(new WordPlay($run, $attempt));
        }

        return $this->gameResponse($run, $result, isset($result['game_over']) ? 409 : 200, $achievements->unlocked());
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

            $run->skipPlate($plates->generate($run->letter_set, $run->ordered));
            $run->save();

            return $this->gameResponse($run, [
                'success' => false,
                'message' => 'Pasamatrícula. Has perdido '.Run::POINTS_PENALTY.' puntos.',
            ]);
        });
    }

    /**
     * Termina la partida (botón "Terminar partida" o fin de la cuenta atrás)
     */
    public function end(Run $run, AchievementUnlocker $achievements): JsonResponse
    {
        Gate::authorize('play', $run);

        $this->finish($run, $achievements);

        return $this->gameResponse($run, [
            'success' => true,
            'message' => 'Partida terminada.',
            'game_over' => true,
            'summary_url' => route('game.summary', $run),
        ], 200, $achievements->unlocked());
    }

    /**
     * "Fin del trayecto": the receipt of a finished run.
     */
    public function summary(Run $run, AchievementUnlocker $achievements, TripReport $report): View|RedirectResponse
    {
        Gate::authorize('play', $run);

        if (! $run->isOver()) {
            return redirect()->route('game.show', $run);
        }

        // A countdown that ran out with the tab closed never called end(); close it now.
        $this->finish($run, $achievements);

        return view('main.summary', [
            'run' => $run,
            'report' => $report->build($run),
            'best' => (int) $run->user->runs()->max('score'),
            'achievements' => $run->user->achievements()->wherePivot('run_id', $run->id)->orderBy('sort')->get(),
        ]);
    }

    /**
     * Marks the run as ended once and evaluates the run achievements.
     */
    private function finish(Run $run, AchievementUnlocker $achievements): void
    {
        $justEnded = $run->withLock(function () use ($run): bool {
            if ($run->ended_at !== null) {
                return false;
            }

            $run->ended_at = now();
            $run->save();

            return true;
        });

        if ($justEnded) {
            $achievements->afterRun($run);
        }
    }

    /**
     * @param  array{success: bool, message: string, game_over?: bool, summary_url?: string}  $result
     * @param  list<array{key: string, name: string, description: string, icon: string}>  $achievements  Unlocked by this request
     */
    private function gameResponse(Run $run, array $result, int $status = 200, array $achievements = []): JsonResponse
    {
        return response()->json([
            ...$result,
            'chars' => $run->plateLetters(),
            'score' => $run->score,
            'achievements' => $achievements,
        ], $status);
    }
}
