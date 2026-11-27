<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One extra plate for the players tied on a podium position. The first valid word wins it.
 *
 * @property int $id
 * @property int $lobby_match_id
 * @property int $position
 * @property list<int> $player_ids
 * @property string $plate
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int|null $winner_id
 * @property string|null $winning_word
 * @property Carbon|null $resolved_at
 * @property-read LobbyMatch $lobbyMatch
 * @property-read User|null $winner
 */
class MatchTiebreak extends Model
{
    /** Seconds of countdown before the plate shows. */
    public const COUNTDOWN_SECONDS = 3;

    /** Time to solve the plate; after it the tied players keep sharing the position. */
    public const SECONDS = 30;

    /**
     * @var list<string>
     */
    protected $fillable = ['lobby_match_id', 'position', 'player_ids', 'plate', 'starts_at', 'ends_at', 'winner_id', 'winning_word', 'resolved_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'player_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LobbyMatch, $this>
     */
    public function lobbyMatch(): BelongsTo
    {
        return $this->belongsTo(LobbyMatch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function includes(User $user): bool
    {
        return in_array($user->id, $this->player_ids, true);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /** The time is up, with the same grace a race gives late words. */
    public function isDue(): bool
    {
        return now()->greaterThan($this->ends_at->copy()->addSeconds(Run::LATENCY_GRACE_SECONDS));
    }

    /** @return list<string> */
    public function plateLetters(): array
    {
        return str_split($this->plate);
    }
}
