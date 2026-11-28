<?php

namespace App\Http\Controllers;

use App\Actions\SubmitWord;
use App\Enums\LobbyFormats;
use App\Http\Requests\SubmitWordRequest;
use App\Models\Lobby;
use App\Models\LobbyMatch;
use App\Models\Run;
use App\Services\FirstWins;
use App\Services\PlateGenerator;
use App\Services\RaceManager;
use App\Services\RaceReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Multiplayer parallel race: the same game screen as solo, on the player's run of the lobby's match.
 */
class RaceController extends Controller
{
    public function __construct(private RaceManager $races, private FirstWins $firstWins) {}

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

        if ($this->firstWins->advanceIfDue($match)) {
            $match->refresh();
            $run->refresh();
        }

        if ($run->isOver() || $match->isFinished()) {
            return redirect()->route('lobbies.results', $lobby);
        }

        $started = ! $match->starts_at->isFuture();
        $round = $started ? $match->currentRound() : 0;

        if ($match->isRounds() && $started) {
            $this->joinRound($run, $match, $round);
        }

        $roundEndsInMs = max(0, (int) now()->diffInMilliseconds($match->roundStartsAt($round + 1), false));
        $plateEndsInMs = $this->plateEndsInMs($match);
        $total = match (true) {
            $match->isRounds() => $match->roundSeconds(),
            $match->isFirstWins() => $match->plateSeconds(),
            default => (int) $run->countdown_seconds,
        };

        return view('main.game', [
            'run' => $run,
            'countdownTotal' => $total,
            // During the traffic light the clock shows the full time.
            'countdownRemaining' => match (true) {
                $match->isRounds() => min($total, (int) ceil($roundEndsInMs / 1000)),
                $match->isFirstWins() => min($total, (int) ceil($plateEndsInMs / 1000)),
                default => min($total, (int) $run->remainingSeconds()),
            },
            'race' => [
                'lobbyId' => $lobby->id,
                'me' => $request->user()->id,
                'startsInMs' => max(0, (int) now()->diffInMilliseconds($match->starts_at, false)),
                // The first plate is only sent once the light is green (then fetched from "sync").
                'plate' => $started ? $run->plate : '',
                'title' => $match->format->shortLabel(),
                'labels' => $match->labels(),
                'canSkip' => $match->format === LobbyFormats::RACE,
                'rounds' => match (true) {
                    $match->isRounds() => [
                        'count' => $match->roundCount(),
                        'seconds' => $match->roundSeconds(),
                        'current' => $round,
                        'endsInMs' => $roundEndsInMs,
                    ],
                    // "First wins" plates last until someone takes them, so the server says when each one ends.
                    $match->isFirstWins() => [
                        'first' => true,
                        'count' => $match->plateCount(),
                        'seconds' => $match->plateSeconds(),
                        'current' => $match->plate_number,
                        'endsInMs' => $plateEndsInMs,
                        'pauseMs' => LobbyMatch::FIRST_PAUSE_MS,
                        'locked' => $started && ! $this->firstWins->canAnswer($run, $match),
                    ],
                    default => null,
                },
                'standings' => $match->standings(),
                'urls' => [
                    'word' => route('lobbies.race.word', $lobby),
                    'plate' => route('lobbies.race.plate', $lobby),
                    'sync' => route('lobbies.race.sync', $lobby),
                    'end' => route('lobbies.race.end', $lobby),
                    'summary' => route('lobbies.results', $lobby),
                    'lobby' => route('lobbies.index'),
                ],
            ],
        ]);
    }

    /** The plate to play now: the first one at the green light, or the new round's. Never before its time. */
    public function sync(Request $request, Lobby $lobby): JsonResponse
    {
        $run = $this->runOrFail($request, $lobby);
        $match = $run->lobbyMatch;

        if ($match === null || $match->starts_at->isFuture()) {
            return response()->json(['success' => false, 'message' => 'Espera al semáforo verde.'], 409);
        }

        if ($match->isRounds()) {
            $this->joinRound($run, $match, $match->currentRound());
        }

        if ($this->firstWins->advanceIfDue($match)) {
            $run->refresh();
        }

        // "First wins" ends when its last plate closes, which the clients learn from here if a broadcast is missed.
        if ($match->isFirstWins() && $run->isOver()) {
            return $this->response($run, ['success' => false, 'message' => 'La partida ha terminado.', 'game_over' => true]);
        }

        return $this->response($run, ['success' => true, 'message' => '']);
    }

    public function word(SubmitWordRequest $request, Lobby $lobby, SubmitWord $submitWord): JsonResponse
    {
        $run = $this->runOrFail($request, $lobby);
        $match = $run->lobbyMatch;
        $before = $run->score;

        $result = $match?->isFirstWins()
            ? $this->firstWins->answer($run, $match, $request->validated('word'), $request->integer('round', $match->plate_number))
            : DB::transaction(function () use ($request, $run, $match, $submitWord): array {
                if ($match?->isRounds()) {
                    // One word at a time per match, so "already said in this round" is decided in order.
                    LobbyMatch::whereKey($match->id)->lockForUpdate()->first();
                    $round = $this->answeredRound($match, $request->integer('round', $match->currentRound()));

                    if ($round === null) {
                        return ['success' => false, 'message' => 'Esa ronda ya ha terminado. Prueba con la matrícula nueva.'];
                    }

                    $this->joinRound($run, $match, $round);
                }

                return $submitWord->handle($run, $request->validated('word'));
            });
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

        $match = $run->lobbyMatch;
        if ($match !== null && $match->format !== LobbyFormats::RACE) {
            return $this->response($run, ['success' => false, 'message' => 'En «'.$match->format->shortLabel().'» no hay Pasamatrícula.'], 422);
        }

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
     * Same shape as the solo game's answers, so the game screen works unchanged. Before the green
     * light no answer carries the plate: it is only handed out from the start time on.
     *
     * @param  array<string, mixed>  $result
     */
    private function response(Run $run, array $result, int $status = 200): JsonResponse
    {
        $match = $run->lobbyMatch;

        return response()->json([
            ...$result,
            'chars' => $run->hasStarted() ? $run->plateLetters() : [],
            'score' => $run->score,
            'round' => $run->plate_number,
            'achievements' => [],
            'first' => $match?->isFirstWins() ? [
                'ends_in_ms' => $this->plateEndsInMs($match),
                'locked' => ! $this->firstWins->canAnswer($run, $match),
            ] : null,
        ], $status);
    }

    /** "First wins": until the current plate stops taking answers. */
    private function plateEndsInMs(LobbyMatch $match): int
    {
        return $match->isFirstWins() ? max(0, (int) now()->diffInMilliseconds($match->plateEndsAt(), false)) : 0;
    }

    /** "Rounds": put the run on the round's plate. */
    private function joinRound(Run $run, LobbyMatch $match, int $round): void
    {
        $run->withLock(function () use ($run, $match, $round): void {
            $run->joinRound($match, $round);
            $run->save();
        });
    }

    /**
     * The round a word answers: the current one, or the previous one if the word left just before
     * the plate changed (the same grace a race gives late words). Null when it is too late.
     */
    private function answeredRound(LobbyMatch $match, int $asked): ?int
    {
        $current = $match->currentRound();
        $lateForPrevious = $asked === $current - 1
            && now()->lessThanOrEqualTo($match->roundStartsAt($current)->addSeconds(Run::LATENCY_GRACE_SECONDS));

        return $asked === $current || $lateForPrevious ? $asked : null;
    }
}
