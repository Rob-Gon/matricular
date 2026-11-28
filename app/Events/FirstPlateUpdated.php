<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "First wins": the shared plate closed (taken or failed by everyone), the next one is ready or the
 * match is over. Never carries the plate itself: clients fetch it from "sync".
 */
class FirstPlateUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  int|null  $nextInMs  set when the plate closed: when the next one is due
     * @param  int|null  $winnerId  who took the closed plate, if anyone
     */
    public function __construct(
        public int $lobbyId,
        public int $plateNumber,
        public ?int $nextInMs = null,
        public ?int $winnerId = null,
        public bool $finished = false,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('lobby.'.$this->lobbyId);
    }

    public function broadcastAs(): string
    {
        return 'race.plate';
    }

    /**
     * @return array{plate: int, next_in_ms: int|null, winner: int|null, finished: bool}
     */
    public function broadcastWith(): array
    {
        return ['plate' => $this->plateNumber, 'next_in_ms' => $this->nextInMs, 'winner' => $this->winnerId, 'finished' => $this->finished];
    }
}
