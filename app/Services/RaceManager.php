<?php

namespace App\Services;

use App\Actions\SubmitWord;
use App\Enums\GameModes;
use App\Enums\LetterSets;
use App\Enums\LobbyFormats;
use App\Events\LobbyUpdated;
use App\Events\RaceScored;
use App\Events\TiebreakUpdated;
use App\Models\Lobby;
use App\Models\LobbyMatch;
use App\Models\MatchTiebreak;
use App\Models\Run;
use App\Models\RunWord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parallel race: every player gets the same seeded plate sequence and plays their own run against
 * the same clock. The server sets the start and end times; clients only display them.
 */
class RaceManager
{
    public const MIN_PLAYERS = 2;

    public function __construct(private PlateGenerator $plates, private SubmitWord $words) {}

    /**
     * @throws ValidationException when the lobby cannot start yet
     */
    public function start(Lobby $lobby): LobbyMatch
    {
        return DB::transaction(function () use ($lobby): LobbyMatch {
            $lobby = Lobby::whereKey($lobby->id)->lockForUpdate()->firstOrFail();
            $players = $lobby->players()->get();

            $problem = match (true) {
                $lobby->status !== Lobby::STATUS_WAITING => 'La partida ya ha empezado.',
                $players->count() < self::MIN_PLAYERS => 'Hacen falta al menos '.self::MIN_PLAYERS.' jugadores.',
                $players->contains('ready', false) => 'Todos tienen que estar listos.',
                default => null,
            };

            if ($problem !== null) {
                throw ValidationException::withMessages(['start' => $problem]);
            }

            $settings = $lobby->settings;
            $letterSet = LetterSets::from($settings['letter_set']);
            $ordered = (bool) $settings['ordered'];
            // Whole seconds, like the runs' end times.
            $startsAt = now()->startOfSecond()->addSeconds(LobbyMatch::LIGHTS_SECONDS + 1);
            $rounds = $lobby->format === LobbyFormats::ROUNDS;
            $first = $lobby->format === LobbyFormats::FIRST;
            $seconds = match ($lobby->format) {
                // Rounds follow each other without a break, so the match lasts every round back to back.
                LobbyFormats::ROUNDS => (int) $settings['rounds'] * (int) $settings['round_seconds'],
                // Plates usually close early; this is the longest it can last (every plate run out, paused and waiting for a word).
                LobbyFormats::FIRST => (int) $settings['plates'] * ((int) $settings['plate_seconds'] + 1 + LobbyMatch::ANSWER_STALE_SECONDS),
                default => (int) $settings['countdown_seconds'],
            };
            $endsAt = $startsAt->copy()->addSeconds($seconds);

            $match = LobbyMatch::create([
                'lobby_id' => $lobby->id,
                'format' => $lobby->format,
                'settings' => $settings,
                // One plate per round, never repeated, so "said in this round" is "said on this plate".
                'plates' => match ($lobby->format) {
                    LobbyFormats::ROUNDS => $this->sequence($letterSet, $ordered, (int) $settings['rounds'], distinct: true),
                    LobbyFormats::FIRST => $this->sequence($letterSet, $ordered, (int) $settings['plates'], distinct: true),
                    default => $this->sequence($letterSet, $ordered),
                },
                'plate_started_at' => $first ? $startsAt : null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            foreach ($players as $player) {
                Run::create([
                    'user_id' => $player->user_id,
                    'lobby_match_id' => $match->id,
                    'score' => 0,
                    'mode' => GameModes::CONTRARELOJ->value,
                    'letter_set' => $letterSet,
                    'ordered' => $ordered,
                    'countdown_time' => $rounds || $first ? null : $settings['countdown_time'],
                    'countdown_seconds' => $seconds,
                    'plate' => $match->plateAt(0),
                    'plate_number' => 0,
                    // Answer times count from the green light.
                    'plate_dealt_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);
            }

            // "Ready" counts for this match only: afterwards "Revancha" marks each player who comes back.
            $lobby->players()->where('user_id', '!=', $lobby->host_id)->update(['ready' => false]);
            $lobby->update(['status' => Lobby::STATUS_PLAYING, 'last_activity_at' => now()]);
            event(LobbyUpdated::from($lobby->fresh() ?? $lobby));

            return $match;
        });
    }

    /** Tells everyone in the lobby a player's new score. */
    public function scored(Run $run): void
    {
        $lobbyId = $run->lobbyMatch?->lobby_id;

        if ($lobbyId !== null) {
            event(new RaceScored($lobbyId, $run->user_id, $run->score));
        }
    }

    /**
     * "Rounds" and "first wins" have no "Pasamatrícula", so a plate passed without a single try costs
     * the same −5: a player far ahead cannot just sit the rest of the match out. Run with the match
     * locked; each run is charged once per plate.
     *
     * @param  list<string>  $plates  plates that are over
     */
    public function chargeIdle(LobbyMatch $match, array $plates): void
    {
        if ($plates === []) {
            return;
        }

        $runs = $match->runs()->get();
        $tried = RunWord::whereIn('run_id', $runs->modelKeys())->whereIn('plate', $plates)
            ->get(['run_id', 'plate'])
            ->groupBy('run_id')
            ->map(fn ($words) => $words->pluck('plate')->all());

        foreach ($runs as $run) {
            $missed = array_diff($plates, $tried[$run->id] ?? []);

            if ($missed === []) {
                continue;
            }

            $run->withLock(function () use ($run, $missed): void {
                foreach ($missed as $plate) {
                    $run->chargeIdle($plate);
                }
                $run->save();
            });
            $this->scored($run);
        }
    }

    /** "Rounds": charges the rounds that are over, every one of them once the clock has run out. */
    public function chargeIdleRounds(LobbyMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $match = LobbyMatch::whereKey($match->id)->lockForUpdate()->first();

            if ($match === null || ! $match->isRounds() || $match->starts_at->isFuture()) {
                return;
            }

            $over = $match->ends_at->isPast() ? $match->roundCount() : $match->currentRound();
            $this->chargeIdle($match, array_map(fn (int $round) => $match->plateAt($round), $over > 0 ? range(0, $over - 1) : []));
        });
    }

    /**
     * Closes the match once every run has ended or the clock has run out, then settles it: podium
     * tiebreaks first, if the lobby plays them, and then back to the starting grid.
     */
    public function finishIfDue(LobbyMatch $match): bool
    {
        return DB::transaction(function () use ($match): bool {
            $match = LobbyMatch::whereKey($match->id)->lockForUpdate()->first();

            if ($match === null || $match->isFinished() || ! $match->isDue()) {
                return false;
            }

            $this->chargeIdleRounds($match);
            $match->update(['finished_at' => now()]);
            // Runs whose page was closed never called "end".
            $match->runs()->whereNull('ended_at')->update(['ended_at' => now()]);
            $this->settle($match);

            return true;
        });
    }

    /**
     * After the race: closes a tiebreak whose time is up, starts the next one while tied players
     * share a podium position, and otherwise sends the lobby back to the grid for the rematch
     * (players are ready once they press "Revancha"). Safe to call any number of times.
     */
    public function settle(LobbyMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $match = LobbyMatch::whereKey($match->id)->lockForUpdate()->first();

            if ($match === null || ! $match->isFinished()) {
                return;
            }

            $active = $match->activeTiebreak();
            if ($active !== null) {
                if (! $active->isDue()) {
                    return;
                }

                // Nobody solved it: the players keep sharing the position.
                $active->update(['resolved_at' => now()]);
            }

            $tie = $match->nextTie();
            if ($tie !== null) {
                $startsAt = now()->addSeconds(MatchTiebreak::COUNTDOWN_SECONDS);
                $match->tiebreaks()->create([
                    ...$tie,
                    'plate' => $this->plates->generate(LetterSets::from($match->settings['letter_set']), (bool) $match->settings['ordered']),
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addSeconds(MatchTiebreak::SECONDS),
                ]);
                $this->tiebreakChanged($match);

                return;
            }

            $lobby = $match->lobby;
            if ($lobby !== null && $lobby->status === Lobby::STATUS_PLAYING && $lobby->latestMatch()?->is($match)) {
                $lobby->update(['status' => Lobby::STATUS_WAITING, 'last_activity_at' => now()]);
                event(LobbyUpdated::from($lobby->fresh() ?? $lobby));
            }
        });
    }

    /**
     * A tied player's answer to the tiebreak plate. The first valid word wins it; wrong words cost
     * nothing, since the race scores are final.
     *
     * @return array{success: bool, message: string}
     */
    public function answerTiebreak(MatchTiebreak $tiebreak, User $user, string $word): array
    {
        return DB::transaction(function () use ($tiebreak, $user, $word): array {
            $tiebreak = MatchTiebreak::whereKey($tiebreak->id)->lockForUpdate()->firstOrFail();

            $problem = match (true) {
                $tiebreak->isResolved() => 'El desempate ya ha terminado.',
                $tiebreak->starts_at->isFuture() => 'Espera a que salga la matrícula.',
                $tiebreak->isDue() => 'Se ha acabado el tiempo del desempate.',
                default => null,
            };

            if ($problem !== null) {
                return ['success' => false, 'message' => $problem];
            }

            $match = $tiebreak->lobbyMatch;
            $verdict = $this->words->judge($word, $tiebreak->plateLetters(), (bool) $match->settings['ordered']);

            if ($verdict === null) {
                return ['success' => false, 'message' => 'No se ha podido verificar la palabra. Inténtalo de nuevo.'];
            }

            if ($verdict['reason'] !== null) {
                return ['success' => false, 'message' => SubmitWord::failure($verdict['reason'])];
            }

            $tiebreak->update([
                'winner_id' => $user->id,
                'winning_word' => $verdict['form'] ?? mb_strtolower(trim($word)),
                'resolved_at' => now(),
            ]);
            $this->tiebreakChanged($match);
            $this->settle($match);

            return ['success' => true, 'message' => '¡Desempate ganado!'];
        });
    }

    /** For the scheduler: races and tiebreaks nobody closed because every player left the page. */
    public function finishOverdue(): int
    {
        $finished = 0;

        LobbyMatch::whereNull('finished_at')
            ->where('ends_at', '<', now()->subSeconds(Run::LATENCY_GRACE_SECONDS))
            ->each(function (LobbyMatch $match) use (&$finished): void {
                $finished += (int) $this->finishIfDue($match);
            });

        MatchTiebreak::whereNull('resolved_at')
            ->where('ends_at', '<', now()->subSeconds(Run::LATENCY_GRACE_SECONDS))
            ->with('lobbyMatch')
            ->each(fn (MatchTiebreak $tiebreak) => $this->settle($tiebreak->lobbyMatch));

        return $finished;
    }

    private function tiebreakChanged(LobbyMatch $match): void
    {
        if ($match->lobby_id !== null) {
            event(new TiebreakUpdated($match->lobby_id));
        }
    }

    /**
     * @return list<string>
     */
    private function sequence(LetterSets $letterSet, bool $ordered, int $length = LobbyMatch::SEQUENCE_LENGTH, bool $distinct = false): array
    {
        $plates = [];

        while (count($plates) < $length) {
            $plate = $this->plates->generate($letterSet, $ordered);

            // Never the same plate twice in a row, or at all when $distinct.
            if ($distinct ? ! in_array($plate, $plates, true) : $plate !== end($plates)) {
                $plates[] = $plate;
            }
        }

        return $plates;
    }
}
