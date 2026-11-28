<?php

namespace App\Models;

use App\Enums\LobbyFormats;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One multiplayer match played in a lobby. Each player's progress is a run (runs.lobby_match_id).
 *
 * @property int $id
 * @property int|null $lobby_id
 * @property LobbyFormats $format
 * @property array<string, mixed> $settings
 * @property list<string> $plates
 * @property int $plate_number
 * @property Carbon|null $plate_started_at
 * @property Carbon|null $plate_closes_at
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $finished_at
 * @property-read Lobby|null $lobby
 * @property-read Collection<int, Run> $runs
 * @property-read Collection<int, MatchTiebreak> $tiebreaks
 */
class LobbyMatch extends Model
{
    /** Seconds of traffic light between "Empezar carrera" and the start. */
    public const LIGHTS_SECONDS = 4;

    /** Length of the shared plate sequence; a player who gets through it starts over. */
    public const SEQUENCE_LENGTH = 400;

    /** "First wins": pause after a plate closes before the next one, so everyone sees who took it. */
    public const FIRST_PAUSE_MS = 1000;

    /** "First wins": the plate waits at most this long for a word still being judged. */
    public const ANSWER_STALE_SECONDS = 5;

    /**
     * @var list<string>
     */
    protected $fillable = ['lobby_id', 'format', 'settings', 'plates', 'plate_number', 'plate_started_at', 'plate_closes_at', 'starts_at', 'ends_at', 'finished_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => LobbyFormats::class,
            'settings' => 'array',
            'plates' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Stored with milliseconds: "first wins" orders answers by arrival.
     *
     * @return Attribute<Carbon|null, Carbon|null>
     */
    protected function plateStartedAt(): Attribute
    {
        return self::milliseconds();
    }

    /**
     * @return Attribute<Carbon|null, Carbon|null>
     */
    protected function plateClosesAt(): Attribute
    {
        return self::milliseconds();
    }

    /**
     * @return Attribute<Carbon|null, Carbon|null>
     */
    private static function milliseconds(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Carbon::parse($value),
            set: fn (?Carbon $value) => $value?->format('Y-m-d H:i:s.v'),
        );
    }

    /**
     * @return BelongsTo<Lobby, $this>
     */
    public function lobby(): BelongsTo
    {
        return $this->belongsTo(Lobby::class);
    }

    /**
     * @return HasMany<Run, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class)->withoutGlobalScope(Run::SOLO_SCOPE);
    }

    /**
     * @return HasMany<MatchTiebreak, $this>
     */
    public function tiebreaks(): HasMany
    {
        return $this->hasMany(MatchTiebreak::class)->orderBy('id');
    }

    public function isRounds(): bool
    {
        return $this->format === LobbyFormats::ROUNDS;
    }

    public function isFirstWins(): bool
    {
        return $this->format === LobbyFormats::FIRST;
    }

    public function plateCount(): int
    {
        return (int) ($this->settings['plates'] ?? 1);
    }

    public function plateSeconds(): int
    {
        return (int) ($this->settings['plate_seconds'] ?? 30);
    }

    public function tieWindowMs(): int
    {
        return (int) ($this->settings['tie_window_ms'] ?? 0);
    }

    /** "First wins": when the current plate runs out of time. */
    public function plateLimitAt(): Carbon
    {
        return ($this->plate_started_at ?? $this->starts_at)->copy()->addSeconds($this->plateSeconds());
    }

    /** "First wins": when the current plate stops taking answers (taken plus the tie window, failed by everyone, or out of time). */
    public function plateEndsAt(): Carbon
    {
        $limit = $this->plateLimitAt();

        return $this->plate_closes_at !== null && $this->plate_closes_at->lessThan($limit) ? $this->plate_closes_at : $limit;
    }

    /** "First wins": who took this plate first, if anyone. */
    public function plateTakenBy(string $plate): ?string
    {
        $run = $this->runs()
            ->join('run_words', 'run_words.run_id', '=', 'runs.id')
            ->where('run_words.plate', $plate)->where('run_words.valid', true)
            ->orderBy('run_words.id')->select('runs.*')->with('user:id,name')->first();

        return $run?->user->name;
    }

    public function roundCount(): int
    {
        return (int) ($this->settings['rounds'] ?? 1);
    }

    public function roundSeconds(): int
    {
        return (int) ($this->settings['round_seconds'] ?? 0);
    }

    /** The round being played now (0-based); rounds follow each other without a break. */
    public function currentRound(): int
    {
        $elapsed = max(0, (int) $this->starts_at->diffInMilliseconds(now()));

        return min($this->roundCount() - 1, intdiv($elapsed, max(1, $this->roundSeconds()) * 1000));
    }

    public function roundStartsAt(int $round): Carbon
    {
        return $this->starts_at->copy()->addSeconds($round * $this->roundSeconds());
    }

    /**
     * Who already scored this word on this plate in the match ("rounds": each plate is one round).
     */
    public function wordTakenBy(string $plate, string $key, int $exceptRunId): ?string
    {
        $run = $this->runs()
            ->whereKeyNot($exceptRunId)
            ->whereHas('runWords', fn ($query) => $query->where('plate', $plate)->where('key', $key)->where('valid', true))
            ->with('user:id,name')
            ->first();

        return $run?->user->name;
    }

    /**
     * The match rules as short labels, like a solo run's settings.
     *
     * @return list<string>
     */
    public function labels(): array
    {
        $s = $this->settings;

        return [
            match ($this->format) {
                LobbyFormats::RACE => $s['countdown_time'] === 'Personalizado'
                    ? 'Carrera de '.intdiv((int) $s['countdown_seconds'], 60).' min'
                    : 'Carrera, '.$s['countdown_time'],
                LobbyFormats::ROUNDS => $this->roundCount().' '.($this->roundCount() === 1 ? 'ronda' : 'rondas').' de '.$this->roundSeconds().' s',
                LobbyFormats::FIRST => $s['plates'].' matrículas',
            },
            ($s['letter_set'] ?? 'simple') === 'simple' ? 'Letras simples' : 'Todas las letras',
            ($s['ordered'] ?? false) ? 'En orden' : 'Cualquier orden',
        ];
    }

    public function activeTiebreak(): ?MatchTiebreak
    {
        return $this->tiebreaks()->whereNull('resolved_at')->first();
    }

    /**
     * Players still sharing a podium position who have not played a tiebreak for it yet, if any.
     * A tiebreak nobody won leaves its players sharing the position for good.
     *
     * @return array{position: int, player_ids: list<int>}|null
     */
    public function nextTie(): ?array
    {
        if (($this->settings['tie_resolution'] ?? null) !== 'tiebreak') {
            return null;
        }

        $played = $this->tiebreaks()->whereNotNull('resolved_at')->whereNull('winner_id')->pluck('position')->all();
        $groups = collect($this->standings())->groupBy('position');

        foreach ($groups as $position => $rows) {
            if ($position <= 3 && $rows->count() > 1 && ! in_array($position, $played, true)) {
                return ['position' => (int) $position, 'player_ids' => $rows->pluck('id')->all()];
            }
        }

        return null;
    }

    /** Plate number $n of the match (0 is the first one). */
    public function plateAt(int $n): string
    {
        return $this->plates[$n % count($this->plates)];
    }

    public function runOf(User $user): ?Run
    {
        return $this->runs()->where('user_id', $user->id)->first();
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /** Every run has ended, or the clock ran out (with the same grace the runs give late words). */
    public function isDue(): bool
    {
        return $this->runs()->whereNull('ended_at')->doesntExist()
            || now()->greaterThan($this->ends_at->copy()->addSeconds(Run::LATENCY_GRACE_SECONDS));
    }

    /**
     * Players by score, best first. Equal scores share the position (1, 1, 3) unless a tiebreak
     * settled it: its winner keeps the position and the other tied players move one down.
     *
     * @return list<array{id: int, name: string, color: string, score: int, position: int}>
     */
    public function standings(): array
    {
        $colors = $this->lobby?->players()->pluck('car_color', 'user_id') ?? collect();
        $rows = $this->runs()->with('user:id,name')->orderByDesc('score')->orderBy('user_id')->get();

        $standings = [];
        foreach ($rows as $index => $run) {
            $previous = $standings[$index - 1] ?? null;
            $standings[] = [
                'id' => $run->user_id,
                'name' => $run->user->name,
                'color' => (string) ($colors[$run->user_id] ?? '#FBF8EF'),
                'score' => $run->score,
                'position' => $previous !== null && $previous['score'] === $run->score ? $previous['position'] : $index + 1,
            ];
        }

        foreach ($this->tiebreaks()->whereNotNull('winner_id')->get() as $tiebreak) {
            foreach ($standings as &$row) {
                if ($row['position'] === $tiebreak->position && in_array($row['id'], $tiebreak->player_ids, true) && $row['id'] !== $tiebreak->winner_id) {
                    $row['position']++;
                }
            }
            unset($row);
        }

        usort($standings, fn (array $a, array $b) => [$a['position'], -$a['score'], $a['id']] <=> [$b['position'], -$b['score'], $b['id']]);

        return $standings;
    }
}
