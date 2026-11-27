<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lobby_id
 * @property int $user_id
 * @property string $car_color
 * @property bool $ready
 * @property Carbon $joined_at
 * @property-read Lobby $lobby
 * @property-read User $user
 */
class LobbyPlayer extends Model
{
    public $timestamps = false;

    // Milliseconds keep the grid order stable when several players join at once.
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * @var list<string>
     */
    protected $fillable = ['lobby_id', 'user_id', 'car_color', 'ready', 'joined_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ready' => 'boolean', 'joined_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Lobby, $this>
     */
    public function lobby(): BelongsTo
    {
        return $this->belongsTo(Lobby::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
