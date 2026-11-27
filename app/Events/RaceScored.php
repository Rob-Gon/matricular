<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A player's new score during a race, for everyone's live scoreboard. Only ids and numbers travel.
 */
class RaceScored implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $lobbyId, public int $userId, public int $score) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('lobby.'.$this->lobbyId);
    }

    public function broadcastAs(): string
    {
        return 'race.scored';
    }

    /**
     * @return array{id: int, score: int}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->userId, 'score' => $this->score];
    }
}
