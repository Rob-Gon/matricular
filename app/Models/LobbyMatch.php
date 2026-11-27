<?php

namespace App\Models;

use App\Enums\LobbyFormats;
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
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $finished_at
 * @property-read Lobby|null $lobby
 * @property-read Collection<int, Run> $runs
 */
class LobbyMatch extends Model
{
    /** Seconds of traffic light between "Empezar carrera" and the start. */
    public const LIGHTS_SECONDS = 4;

    /** Length of the shared plate sequence; a player who gets through it starts over. */
    public const SEQUENCE_LENGTH = 400;

    /**
     * @var list<string>
     */
    protected $fillable = ['lobby_id', 'format', 'settings', 'plates', 'starts_at', 'ends_at', 'finished_at'];

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
     * Players by score, best first. Equal scores share the position (1, 1, 3).
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

        return $standings;
    }
}
