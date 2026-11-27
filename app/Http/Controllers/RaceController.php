<?php

namespace App\Http\Controllers;

use App\Actions\SubmitWord;
use App\Http\Requests\SubmitWordRequest;
use App\Models\Lobby;
use App\Models\LobbyMatch;
use App\Models\Run;
use App\Services\PlateGenerator;
use App\Services\RaceManager;
use App\Services\RaceReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Multiplayer parallel race: the same game screen as solo, on the player's run of the lobby's match.
 */
class RaceController extends Controller
{
    public function __construct(private RaceManager $races) {}

    public function start(Lobby $lobby): JsonResponse
    {
        Gate::authorize('manage', $lobby);
        $this->races->start($lobby);

        return response()->json(($lobby->fresh() ?? $lobby)->state());
    }

    public function show(Request $request, Lobby $lobby): View|RedirectResponse
    {
        [$match, $run] = $this->current($request, $lobby);

        if ($run === null) {
            return redirect()->route('lobbies.show', $lobby);
        }

        if ($run->isOver() || $match->isFinished()) {
            return redirect()->route('lobbies.results', $lobby);
        }

        $total = (int) $run->countdown_seconds;

        return view('main.game', [
            'run' => $run,
            'countdownTotal' => $total,
            // During the traffic light the clock shows the full time.
            'countdownRemaining' => min($total, (int) $run->remainingSeconds()),
            'race' => [
                'lobbyId' => $lobby->id,
                'me' => $request->user()->id,
                'startsInMs' => max(0, (int) now()->diffInMilliseconds($match->starts_at, false)),
                'standings' => $match->standings(),
                'urls' => [
                    'word' => route('lobbies.race.word', $lobby),
                    'plate' => route('lobbies.race.plate', $lobby),
                    'end' => route('lobbies.race.end', $lobby),
                    'summary' => route('lobbies.results', $lobby),
                    'lobby' => route('lobbies.index'),
                ],
            ],
        ]);
    }

    public function word(SubmitWordRequest $request, Lobby $lobby, SubmitWord $submitWord): JsonResponse
    {
        $run = $this->runOrFail($request, $lobby);
        $before = $run->score;

        $result = $submitWord->handle($run, $request->validated('word'));
        Arr::forget($result, 'attempt'); // multiplayer runs unlock no achievements

        if ($run->score !== $before) {
            $this->races->scored($run);
        }

        return $this->response($run, $result, isset($result['game_over']) ? 409 : 200);
    }

    public function plate(Request $request, Lobby $lobby, PlateGenerator $plates): JsonResponse
    {
        $run = $this->runOrFail($request, $lobby);
        $before = $run->score;

        $result = $run->withLock(function () use ($run, $plates): array {
            if ($run->isOver()) {
                return ['success' => false, 'message' => 'La carrera ha terminado.', 'game_over' => true];
            }

            if (! $run->hasStarted()) {
                return ['success' => false, 'message' => 'Espera al semáforo verde.'];
            }

            $run->skipPlate($run->nextPlate($plates));
            $run->save();

            return ['success' => false, 'message' => 'Pasamatrícula. Has perdido '.Run::POINTS_PENALTY.' puntos.'];
        });

        if ($run->score !== $before) {
            $this->races->scored($run);
        }

        return $this->response($run, $result, isset($result['game_over']) ? 409 : 200);
    }

    /** The player's clock ran out (or the page asks to close the run): end it and maybe the race. */
    public function end(Request $request, Lobby $lobby): JsonResponse
    {
        $run = $this->runOrFail($request, $lobby);

        $run->withLock(function () use ($run): void {
            // Only once the clock is up: a player cannot stop early and freeze their score.
            if ($run->ended_at === null && $run->ends_at?->isPast()) {
                $run->ended_at = now();
                $run->save();
            }
        });

        if ($run->lobbyMatch !== null) {
            $this->races->finishIfDue($run->lobbyMatch);
        }

        return $this->response($run, ['success' => true, 'message' => 'Carrera terminada.', 'game_over' => true]);
    }

    public function results(Request $request, Lobby $lobby, RaceReport $report): View|RedirectResponse
    {
        [$match, $run] = $this->current($request, $lobby);

        if ($run === null) {
            return redirect()->route('lobbies.show', $lobby);
        }

        // Whoever looks first closes a race (or a tiebreak) whose clock has run out.
        $this->races->finishIfDue($match);
        $this->races->settle($match);
        $match->refresh();

        $tiebreak = $match->activeTiebreak();
        $standings = collect($match->standings());
        // The page reloads when the race or the tiebreak should be over, in case a broadcast is missed.
        $reloadAt = $tiebreak !== null ? $tiebreak->ends_at : ($match->isFinished() ? null : $match->ends_at);

        return view('lobbies.results', [
            'lobby' => $lobby,
            'match' => $match,
            'run' => $run,
            'report' => $report->build($match),
            'tiebreak' => $tiebreak,
            'tiedNames' => $tiebreak ? $standings->whereIn('id', $tiebreak->player_ids)->pluck('name')->all() : [],
            'settled' => $match->tiebreaks()->whereNotNull('resolved_at')->with('winner:id,name')->get(),
            'config' => [
                'lobbyId' => $lobby->id,
                'watching' => $reloadAt !== null,
                'reloadInMs' => $reloadAt === null ? null : max(0, (int) now()->diffInMilliseconds($reloadAt, false)) + (Run::LATENCY_GRACE_SECONDS + 1) * 1000,
                'tiebreak' => $tiebreak === null ? null : [
                    'playing' => $tiebreak->includes($request->user()),
                    'startsInMs' => max(0, (int) now()->diffInMilliseconds($tiebreak->starts_at, false)),
                    'seconds' => (int) max(0, ceil(now()->diffInSeconds($tiebreak->ends_at, false))),
                ],
                'csrf' => csrf_token(),
                'urls' => ['index' => route('lobbies.index'), 'tiebreak' => route('lobbies.tiebreak', $lobby)],
            ],
        ]);
    }

    /** A tied player's word for the tiebreak plate. */
    public function tiebreak(SubmitWordRequest $request, Lobby $lobby): JsonResponse
    {
        Gate::authorize('view', $lobby);
        $tiebreak = $lobby->latestMatch()?->activeTiebreak();

        abort_if($tiebreak === null, 409, 'El desempate ya ha terminado.');
        abort_unless($tiebreak->includes($request->user()), 403);

        return response()->json($this->races->answerTiebreak($tiebreak, $request->user(), $request->validated('word')));
    }

    /**
     * The lobby's latest match and the player's run in it. Only players of the lobby get this far.
     *
     * @return array{0: LobbyMatch|null, 1: Run|null}
     */
    private function current(Request $request, Lobby $lobby): array
    {
        Gate::authorize('view', $lobby);
        $match = $lobby->latestMatch();

        return [$match, $match?->runOf($request->user())];
    }

    private function runOrFail(Request $request, Lobby $lobby): Run
    {
        [, $run] = $this->current($request, $lobby);
        abort_if($run === null, 404);

        return $run;
    }

    /**
     * Same shape as the solo game's answers, so the game screen works unchanged.
     *
     * @param  array<string, mixed>  $result
     */
    private function response(Run $run, array $result, int $status = 200): JsonResponse
    {
        return response()->json([...$result, 'chars' => $run->plateLetters(), 'score' => $run->score, 'achievements' => []], $status);
    }
}
