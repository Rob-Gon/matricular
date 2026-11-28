<?php

namespace App\Services;

use App\Actions\SubmitWord;
use App\Events\FirstPlateUpdated;
use App\Models\LobbyMatch;
use App\Models\Run;
use App\Models\RunWord;
use App\Support\WordNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "First wins": everyone plays the same plate. The first valid word takes it, valid words that
 * arrive within the tie window also score, and then the plate changes for everybody. Each player
 * has one try per plate (a wrong word costs the usual penalty); the plate also passes, with no
 * penalty, once everyone has failed it or its time is up.
 *
 * Words are judged outside the match lock, so a slow dictionary lookup never holds the others up,
 * and ordered by arrival time. Every change to the shared plate is made with the match row locked.
 */
class FirstWins
{
    public function __construct(private SubmitWord $words, private Dictionary $dictionary, private RaceManager $races) {}

    /**
     * @param  int  $asked  the plate number the player was answering
     * @return array{success: bool, message: string, game_over?: bool}
     */
    public function answer(Run $run, LobbyMatch $match, string $input, int $asked): array
    {
        // Taken before anything else: answers are ordered by when they reached the server.
        $arrivedAt = now();
        $this->markAnswering($run, $arrivedAt);

        try {
            return $this->score($run, $match, trim($input), $asked, $arrivedAt);
        } finally {
            $this->markAnswering($run, null);
            // The next plate may have been waiting for this word only.
            $this->advanceIfDue($match);
            $run->refresh();
        }
    }

    /**
     * Deals the next plate (or ends the match) once the current one has closed, the pause after it
     * is over and no word that arrived in time is still being judged. Safe to call any number of times.
     */
    public function advanceIfDue(LobbyMatch $match): bool
    {
        return DB::transaction(function () use ($match): bool {
            $match = LobbyMatch::whereKey($match->id)->lockForUpdate()->first();

            if ($match === null || ! $match->isFirstWins() || $match->isFinished() || $match->starts_at->isFuture()) {
                return false;
            }

            $endsAt = $match->plateEndsAt();
            if (now()->lessThan($endsAt->copy()->addMilliseconds(LobbyMatch::FIRST_PAUSE_MS)) || $this->answerPending($match, $endsAt)) {
                return false;
            }

            $next = $match->plate_number + 1;

            if ($next >= $match->plateCount()) {
                $match->update(['finished_at' => now(), 'ends_at' => now()]);
                $match->runs()->whereNull('ended_at')->update(['ended_at' => now()]);
                $this->broadcast($match, new FirstPlateUpdated((int) $match->lobby_id, $next, finished: true));
                $this->races->settle($match);

                return true;
            }

            $match->update(['plate_number' => $next, 'plate_started_at' => now(), 'plate_closes_at' => null]);
            $match->runs()->update([
                'plate' => $match->plateAt($next),
                'plate_number' => $next,
                'plate_dealt_at' => now()->format('Y-m-d H:i:s.v'),
            ]);
            $this->broadcast($match, new FirstPlateUpdated((int) $match->lobby_id, $next));

            return true;
        });
    }

    /** Whether the player can still answer the current plate. */
    public function canAnswer(Run $run, LobbyMatch $match): bool
    {
        return now()->lessThanOrEqualTo($match->plateEndsAt()) && ! $run->hasTriedPlate($match->plateAt($match->plate_number));
    }

    /**
     * @return array{success: bool, message: string, game_over?: bool}
     */
    private function score(Run $run, LobbyMatch $match, string $input, int $asked, Carbon $arrivedAt): array
    {
        $match->refresh();
        $number = $match->plate_number;
        $plate = $match->plateAt($number);
        $key = WordNormalizer::key($input);

        $problem = $this->problem($run, $match, $number, $asked, $plate, $arrivedAt);
        if ($problem !== null) {
            return $problem;
        }

        $verdict = $run->hasCorrectWord($key) ? null : $this->words->judge($input, str_split($plate), $run->ordered);
        $categories = $verdict !== null && $verdict['reason'] === null ? $this->dictionary->categories($key) : [];

        return DB::transaction(function () use ($run, $match, $input, $key, $number, $asked, $plate, $arrivedAt, $verdict, $categories): array {
            $match = LobbyMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            return $run->withLock(function () use ($run, $match, $input, $key, $number, $asked, $plate, $arrivedAt, $verdict, $categories): array {
                // The state may have changed while the word was being judged.
                $problem = $this->problem($run, $match, $number, $asked, $plate, $arrivedAt);
                if ($problem !== null) {
                    return $problem;
                }

                if ($run->hasCorrectWord($key)) {
                    $run->addRepeatedWord($key, $input);

                    return ['success' => false, 'message' => 'Ya has usado esa palabra en esta partida. No suma puntos: prueba con otra.'];
                }

                if ($verdict === null) {
                    return ['success' => false, 'message' => 'No se ha podido verificar la palabra. Inténtalo de nuevo, no has perdido puntos.'];
                }

                if ($verdict['reason'] !== null) {
                    $run->addErrorWord($key, $input, $verdict['reason']);
                    $run->save();
                    $this->closeIfEveryoneFailed($match, $plate);

                    return [
                        'success' => false,
                        'message' => SubmitWord::failure($verdict['reason']).' Has perdido '.Run::POINTS_PENALTY.' puntos. Espera a la siguiente matrícula.',
                    ];
                }

                $run->addCorrectWord($key, $input, $verdict['form'], $categories);
                $run->save();

                if ($match->plate_closes_at === null) {
                    $match->update(['plate_closes_at' => $arrivedAt->copy()->addMilliseconds($match->tieWindowMs())]);
                    $this->broadcastClosed($match, $run->user_id);

                    return ['success' => true, 'message' => '¡Te llevas la matrícula! Has ganado '.Run::POINTS_CORRECT.' puntos.'];
                }

                return ['success' => true, 'message' => '¡Empate al milímetro! También ganas '.Run::POINTS_CORRECT.' puntos.'];
            });
        });
    }

    /**
     * Why this answer cannot score, or null if it still can.
     *
     * @return array{success: bool, message: string, game_over?: bool}|null
     */
    private function problem(Run $run, LobbyMatch $match, int $number, int $asked, string $plate, Carbon $arrivedAt): ?array
    {
        if ($match->isFinished() || $run->isOver()) {
            return ['success' => false, 'message' => 'La partida ha terminado.', 'game_over' => true];
        }

        if ($match->starts_at->isFuture()) {
            return ['success' => false, 'message' => 'Espera al semáforo verde.'];
        }

        if ($match->plate_number !== $number || $asked !== $number) {
            return ['success' => false, 'message' => 'La matrícula ya ha cambiado. Prueba con la nueva.'];
        }

        if ($run->hasTriedPlate($plate)) {
            return ['success' => false, 'message' => 'Ya has probado con esta matrícula. Espera a la siguiente.'];
        }

        if ($arrivedAt->greaterThan($match->plateEndsAt())) {
            $winner = $match->plateTakenBy($plate);

            return ['success' => false, 'message' => $winner !== null
                ? "Tarde: {$winner} ya se ha llevado la matrícula. No pierdes puntos."
                : 'Se ha acabado el tiempo de esta matrícula. No pierdes puntos.'];
        }

        return null;
    }

    private function closeIfEveryoneFailed(LobbyMatch $match, string $plate): void
    {
        if ($match->plate_closes_at !== null) {
            return;
        }

        $failed = $match->runs()
            ->whereHas('runWords', fn ($query) => $query->where('plate', $plate)->where('valid', false)->whereNotIn('reason', RunWord::NEUTRAL_REASONS))
            ->count();

        if ($failed === $match->runs()->count()) {
            $match->update(['plate_closes_at' => now()]);
            $this->broadcastClosed($match, null);
        }
    }

    /** A word that reached the server before the plate closed is still being judged. */
    private function answerPending(LobbyMatch $match, Carbon $endsAt): bool
    {
        return $match->runs()
            ->where('answering_since', '<=', $endsAt->format('Y-m-d H:i:s.v'))
            // A request that died half way must not hold the plate forever.
            ->where('answering_since', '>', now()->subSeconds(LobbyMatch::ANSWER_STALE_SECONDS)->format('Y-m-d H:i:s.v'))
            ->exists();
    }

    private function markAnswering(Run $run, ?Carbon $at): void
    {
        Run::withoutGlobalScope(Run::SOLO_SCOPE)->whereKey($run->id)->toBase()
            ->update(['answering_since' => $at?->format('Y-m-d H:i:s.v')]);
    }

    private function broadcastClosed(LobbyMatch $match, ?int $winnerId): void
    {
        $nextInMs = max(0, (int) now()->diffInMilliseconds($match->plateEndsAt(), false)) + LobbyMatch::FIRST_PAUSE_MS;
        $this->broadcast($match, new FirstPlateUpdated((int) $match->lobby_id, $match->plate_number, $nextInMs, $winnerId));
    }

    private function broadcast(LobbyMatch $match, FirstPlateUpdated $event): void
    {
        if ($match->lobby_id !== null) {
            event($event);
        }
    }
}
