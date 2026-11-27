<?php

namespace App\Services;

use App\Enums\GameModes;
use App\Enums\LetterSets;
use App\Enums\LobbyFormats;
use App\Events\LobbyUpdated;
use App\Events\RaceScored;
use App\Models\Lobby;
use App\Models\LobbyMatch;
use App\Models\Run;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parallel race: every player gets the same seeded plate sequence and plays their own run against
 * the same clock. The server sets the start and end times; clients only display them.
 */
class RaceManager
{
    public const MIN_PLAYERS = 2;

    public function __construct(private PlateGenerator $plates) {}

    /**
     * @throws ValidationException when the lobby cannot start yet
     */
    public function start(Lobby $lobby): LobbyMatch
    {
        return DB::transaction(function () use ($lobby): LobbyMatch {
            $lobby = Lobby::whereKey($lobby->id)->lockForUpdate()->firstOrFail();
            $players = $lobby->players()->get();

            $problem = match (true) {
                $lobby->status !== Lobby::STATUS_WAITING => 'La carrera ya ha empezado.',
                $lobby->format !== LobbyFormats::RACE => 'Ese formato todavía no se puede jugar. Elige «Carrera» en las reglas.',
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
            $endsAt = $startsAt->copy()->addSeconds((int) $settings['countdown_seconds']);

            $match = LobbyMatch::create([
                'lobby_id' => $lobby->id,
                'format' => $lobby->format,
                'settings' => $settings,
                'plates' => $this->sequence($letterSet, $ordered),
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
                    'countdown_time' => $settings['countdown_time'],
                    'countdown_seconds' => $settings['countdown_seconds'],
                    'plate' => $match->plateAt(0),
                    'plate_number' => 0,
                    // Answer times count from the green light.
                    'plate_dealt_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);
            }

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
     * Closes the match once every run has ended or the clock has run out, and sends the lobby back
     * to the starting grid for the rematch (everyone but the host confirms "ready" again).
     */
    public function finishIfDue(LobbyMatch $match): bool
    {
        return DB::transaction(function () use ($match): bool {
            $match = LobbyMatch::whereKey($match->id)->lockForUpdate()->first();

            if ($match === null || $match->isFinished() || ! $match->isDue()) {
                return false;
            }

            $match->update(['finished_at' => now()]);
            // Runs whose page was closed never called "end".
            $match->runs()->whereNull('ended_at')->update(['ended_at' => now()]);

            $lobby = $match->lobby;
            if ($lobby !== null) {
                $lobby->update(['status' => Lobby::STATUS_WAITING, 'last_activity_at' => now()]);
                $lobby->players()->where('user_id', '!=', $lobby->host_id)->update(['ready' => false]);
                event(LobbyUpdated::from($lobby->fresh() ?? $lobby));
            }

            return true;
        });
    }

    /** For the scheduler: races nobody closed because every player left the page. */
    public function finishOverdue(): int
    {
        $finished = 0;

        LobbyMatch::whereNull('finished_at')
            ->where('ends_at', '<', now()->subSeconds(Run::LATENCY_GRACE_SECONDS))
            ->each(function (LobbyMatch $match) use (&$finished): void {
                $finished += (int) $this->finishIfDue($match);
            });

        return $finished;
    }

    /**
     * @return list<string>
     */
    private function sequence(LetterSets $letterSet, bool $ordered): array
    {
        $plates = [];

        while (count($plates) < LobbyMatch::SEQUENCE_LENGTH) {
            $plate = $this->plates->generate($letterSet, $ordered);

            // Never the same plate twice in a row.
            if ($plate !== end($plates)) {
                $plates[] = $plate;
            }
        }

        return $plates;
    }
}
