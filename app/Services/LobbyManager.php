<?php

namespace App\Services;

use App\Enums\LobbyFormats;
use App\Events\LobbyUpdated;
use App\Models\Lobby;
use App\Models\LobbyPlayer;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Every change to a lobby goes through here: it runs with the lobby row locked, refreshes the idle
 * timer and broadcasts the new state to everyone in the room.
 */
class LobbyManager
{
    public function create(User $host, LobbyFormats $format, ?string $password): Lobby
    {
        return DB::transaction(function () use ($host, $format, $password): Lobby {
            $this->leaveCurrent($host);

            $lobby = Lobby::create([
                'code' => $this->freeCode(),
                'host_id' => $host->id,
                'password' => $password === null || $password === '' ? null : $password,
                'format' => $format,
                'settings' => $format->defaults(),
                'status' => Lobby::STATUS_WAITING,
                'last_activity_at' => now(),
            ]);

            $this->seat($lobby, $host);

            return $lobby;
        });
    }

    /**
     * @throws ValidationException when the room is closed to new players or the password is wrong
     */
    public function join(User $user, string $code, ?string $password): Lobby
    {
        $lobby = Lobby::where('code', strtoupper(trim($code)))->first();

        if ($lobby === null) {
            throw ValidationException::withMessages(['code' => 'No hay ninguna sala con ese código.']);
        }

        if ($lobby->hasPlayer($user)) {
            return $lobby;
        }

        if ($lobby->password !== null && ! Hash::check((string) $password, $lobby->password)) {
            throw ValidationException::withMessages(['join_password' => 'La contraseña de la sala no es correcta.']);
        }

        return $this->change($lobby, function (Lobby $lobby) use ($user): void {
            if ($lobby->wasKicked($user)) {
                throw ValidationException::withMessages(['code' => 'El anfitrión te sacó de esta sala y no puedes volver a entrar.']);
            }

            if ($lobby->status !== Lobby::STATUS_WAITING) {
                throw ValidationException::withMessages(['code' => 'La partida de esa sala ya ha empezado.']);
            }

            if ($lobby->players()->count() >= Lobby::MAX_PLAYERS) {
                throw ValidationException::withMessages(['code' => 'La sala está llena: ya hay '.Lobby::MAX_PLAYERS.' jugadores.']);
            }

            $this->leaveCurrent($user);
            $this->seat($lobby, $user);
        });
    }

    public function leave(Lobby $lobby, User $user): void
    {
        $this->removePlayer($lobby, $user->id);
    }

    public function kick(Lobby $lobby, int $userId): void
    {
        if ($userId === $lobby->host_id) {
            throw ValidationException::withMessages(['player' => 'El anfitrión no puede expulsarse a sí mismo.']);
        }

        if (! $lobby->players()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['player' => 'Ese jugador ya no está en la sala.']);
        }

        $this->removePlayer($lobby, $userId, kicked: true);
    }

    public function setReady(Lobby $lobby, User $user, bool $ready): Lobby
    {
        return $this->change($lobby, fn (Lobby $lobby) => $this->player($lobby, $user)->update(['ready' => $ready]));
    }

    public function setColor(Lobby $lobby, User $user, string $color): Lobby
    {
        return $this->change($lobby, fn (Lobby $lobby) => $this->player($lobby, $user)->update(['car_color' => $color]));
    }

    /**
     * New rules change the race for everybody, so every player has to confirm "ready" again.
     *
     * @param  array<string, mixed>  $settings
     */
    public function setRules(Lobby $lobby, LobbyFormats $format, array $settings): Lobby
    {
        return $this->change($lobby, function (Lobby $lobby) use ($format, $settings): void {
            $lobby->update(['format' => $format, 'settings' => $settings]);
            $lobby->players()->where('user_id', '!=', $lobby->host_id)->update(['ready' => false]);
        });
    }

    /**
     * Closes lobbies nobody has touched for a while.
     */
    public function closeIdle(): int
    {
        $closed = 0;

        Lobby::where('last_activity_at', '<', now()->subMinutes(Lobby::IDLE_MINUTES))
            ->where('status', '!=', Lobby::STATUS_PLAYING)
            ->each(function (Lobby $lobby) use (&$closed): void {
                event(LobbyUpdated::from($lobby, closed: true));
                $lobby->delete();
                $closed++;
            });

        return $closed;
    }

    private function removePlayer(Lobby $lobby, int $userId, bool $kicked = false): void
    {
        $lobby->getConnection()->transaction(function () use ($lobby, $userId, $kicked): void {
            $locked = Lobby::whereKey($lobby->id)->lockForUpdate()->first();
            $lobby->players()->where('user_id', $userId)->delete();

            if ($kicked && $locked !== null) {
                $lobby->update(['kicked_user_ids' => array_values(array_unique([...$locked->kicked_user_ids ?? [], $userId]))]);
            }

            $next = $lobby->players()->first();

            if ($next === null) {
                $lobby->delete();

                return;
            }

            // The player who has been in the room longest takes over.
            $lobby->update([
                'host_id' => $lobby->host_id === $userId ? $next->user_id : $lobby->host_id,
                'last_activity_at' => now(),
            ]);

            event(LobbyUpdated::from($lobby->fresh() ?? $lobby));
        });
    }

    /**
     * @param  Closure(Lobby): mixed  $change
     */
    private function change(Lobby $lobby, Closure $change): Lobby
    {
        return DB::transaction(function () use ($lobby, $change): Lobby {
            $locked = Lobby::whereKey($lobby->id)->lockForUpdate()->firstOrFail();
            $change($locked);
            $locked->update(['last_activity_at' => now()]);

            $fresh = $locked->fresh() ?? $locked;
            event(LobbyUpdated::from($fresh));

            return $fresh;
        });
    }

    private function seat(Lobby $lobby, User $user): void
    {
        // The least used colour, so a fresh grid looks varied; players can change it.
        $used = $lobby->players()->pluck('car_color')->countBy();
        $color = collect(array_keys(Lobby::CAR_COLORS))->sortBy(fn (string $color) => $used[$color] ?? 0)->first();

        LobbyPlayer::create([
            'lobby_id' => $lobby->id,
            'user_id' => $user->id,
            'car_color' => $color,
            'ready' => $lobby->host_id === $user->id,
            'joined_at' => now(),
        ]);
    }

    private function player(Lobby $lobby, User $user): LobbyPlayer
    {
        return $lobby->players()->where('user_id', $user->id)->firstOrFail();
    }

    /** A player is in one lobby at a time: joining another one leaves the previous room. */
    private function leaveCurrent(User $user): void
    {
        $current = LobbyPlayer::where('user_id', $user->id)->with('lobby')->first();

        if ($current !== null) {
            $this->removePlayer($current->lobby, $user->id);
        }
    }

    /** Codes look like a plate ("4821 KTZ"): letters from the simple set, so no vowels and no words. */
    private function freeCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT)
                .implode('', array_map(fn () => PlateGenerator::SIMPLE_LETTERS[array_rand(PlateGenerator::SIMPLE_LETTERS)], range(1, 3)));
        } while (Lobby::where('code', $code)->exists());

        return $code;
    }
}
