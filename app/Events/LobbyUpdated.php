<?php

namespace App\Events;

use App\Models\Lobby;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The whole lobby state after any change (join, leave, kick, ready, colour, rules). Lobbies are small,
 * so sending the full state keeps every client in sync without merging partial updates.
 */
class LobbyUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $state
     */
    public function __construct(public int $lobbyId, public array $state) {}

    public static function from(Lobby $lobby, bool $closed = false): self
    {
        return new self($lobby->id, [...$lobby->state(), 'closed' => $closed]);
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('lobby.'.$this->lobbyId);
    }

    public function broadcastAs(): string
    {
        return 'lobby.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->state;
    }
}
