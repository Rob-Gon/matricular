<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A player's quick reaction (an emoji bubble over their car), horn or shout. Only the player id
 * travels; $horn and $shout are random numbers every client turns into the same sound.
 */
class LobbyReacted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $lobbyId, public int $userId, public ?string $emoji = null, public ?int $horn = null, public ?int $shout = null) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('lobby.'.$this->lobbyId);
    }

    public function broadcastAs(): string
    {
        return 'lobby.reaction';
    }

    /**
     * @return array{id: int, emoji: string|null, horn: int|null, shout: int|null}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->userId, 'emoji' => $this->emoji, 'horn' => $this->horn, 'shout' => $this->shout];
    }
}
