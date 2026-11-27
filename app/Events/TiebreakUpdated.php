<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A tiebreak started or was won: the results pages reload to show it.
 */
class TiebreakUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $lobbyId) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('lobby.'.$this->lobbyId);
    }

    public function broadcastAs(): string
    {
        return 'race.tiebreak';
    }

    /**
     * @return array<string, never>
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
