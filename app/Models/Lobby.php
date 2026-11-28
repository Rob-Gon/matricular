<?php

namespace App\Models;

use App\Enums\LetterSets;
use App\Enums\LobbyFormats;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int $host_id
 * @property string|null $password
 * @property LobbyFormats $format
 * @property array<string, mixed> $settings
 * @property string $status
 * @property list<int>|null $kicked_user_ids
 * @property Carbon $last_activity_at
 * @property-read Collection<int, LobbyPlayer> $players
 */
class Lobby extends Model
{
    public const MAX_PLAYERS = 16;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_PLAYING = 'playing';

    public const STATUS_FINISHED = 'finished';

    /** Minutes without activity after which a lobby closes. */
    public const IDLE_MINUTES = 30;

    /** Car colours on the starting grid, by name. */
    public const CAR_COLORS = [
        '#E9A92B' => 'Mostaza', '#C8361F' => 'Rojo', '#1F3A97' => 'Azul', '#FBF8EF' => 'Crema',
        '#9FC4BE' => 'Verde agua', '#F07A5C' => 'Coral', '#3A3A36' => 'Asfalto', '#9C7BB8' => 'Lila',
    ];

    /** Quick reactions shown as a bubble over the player's car, with their accessible names. */
    public const REACTIONS = [
        '👏' => 'Aplauso', '😂' => 'Risa', '😱' => 'Sorpresa', '🔥' => 'Fuego',
        '😭' => 'Llanto', '👀' => 'Ojos', '🤯' => 'Cabeza explotando', '💀' => 'Calavera',
    ];

    /** Seconds a player waits between horns. */
    public const SOUND_COOLDOWN_SECONDS = 3;

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'host_id', 'password', 'format', 'settings', 'status', 'kicked_user_ids', 'last_activity_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => LobbyFormats::class,
            'settings' => 'array',
            'kicked_user_ids' => 'array',
            'password' => 'hashed',
            'last_activity_at' => 'datetime',
        ];
    }

    public function wasKicked(User $user): bool
    {
        return in_array($user->id, $this->kicked_user_ids ?? [], true);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * Players in grid order: whoever joined first is at the front.
     *
     * @return HasMany<LobbyPlayer, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(LobbyPlayer::class)->orderBy('joined_at')->orderBy('id');
    }

    /**
     * @return HasMany<LobbyMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(LobbyMatch::class);
    }

    /** The match being played, or the last one played. */
    public function latestMatch(): ?LobbyMatch
    {
        return $this->matches()->latest('id')->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function hasPlayer(User $user): bool
    {
        return $this->players()->where('user_id', $user->id)->exists();
    }

    /**
     * The rules as Spanish term/value pairs for the lobby screen.
     *
     * @return list<array{term: string, value: string}>
     */
    public function ruleLabels(): array
    {
        $s = $this->settings;
        $rules = match ($this->format) {
            LobbyFormats::RACE => [
                ['term' => 'Duración', 'value' => isset($s['countdown_seconds']) && $s['countdown_time'] === 'Personalizado'
                    ? intdiv((int) $s['countdown_seconds'], 60).' minutos (personalizado)'
                    : (string) $s['countdown_time']],
                ['term' => 'Pasamatrícula', 'value' => 'Sí, −'.Run::POINTS_PENALTY],
            ],
            LobbyFormats::ROUNDS => [
                ['term' => 'Rondas', 'value' => $s['rounds'].' de '.$s['round_seconds'].' segundos'],
                ['term' => 'Palabras repetidas', 'value' => 'Si otro ya la dijo en la ronda, no suma'],
            ],
            LobbyFormats::FIRST => [
                ['term' => 'Matrículas', 'value' => $s['plates'].', '.$s['plate_seconds'].' s cada una como máximo'],
                ['term' => 'Casi a la vez', 'value' => $s['tie_window_ms'] > 0 ? 'Puntúan todos los que acierten en '.$s['tie_window_ms'].' ms' : 'Solo puntúa el primero'],
                ['term' => 'Si todos fallan', 'value' => 'La matrícula pasa sin quitar puntos'],
            ],
        };

        $letters = ($s['letter_set'] ?? 'simple') === LetterSets::SIMPLE->value ? 'Simples' : 'Todas';

        return [
            ...$rules,
            ['term' => 'Letras', 'value' => $letters.', '.(($s['ordered'] ?? false) ? 'en el orden de la matrícula' : 'en cualquier orden')],
            ['term' => 'Empate final', 'value' => ($s['tie_resolution'] ?? 'tiebreak') === 'shared'
                ? 'Los empatados comparten puesto'
                : 'Matrícula extra solo para los empatados del podio'],
        ];
    }

    /**
     * Everything the lobby screen shows; sent on load and broadcast after every change.
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $this->loadMissing('players.user');

        return [
            'code' => $this->code,
            'status' => $this->status,
            'format' => $this->format->value,
            'formatLabel' => $this->format->shortLabel().'. '.$this->format->description().'.',
            'settings' => $this->settings,
            'rules' => $this->ruleLabels(),
            'hostId' => $this->host_id,
            'locked' => $this->password !== null,
            'players' => $this->players->map(fn (LobbyPlayer $player) => [
                'id' => $player->user_id,
                'name' => $player->user->name,
                'color' => $player->car_color,
                'ready' => $player->ready,
            ])->values()->all(),
        ];
    }
}
