<?php

namespace App\Http\Controllers;

use App\Events\LobbyReacted;
use App\Http\Requests\Lobbies\ReactRequest;
use App\Models\Lobby;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Quick reactions on the grid and during a match; horns and shouts on the grid only. How often a
 * player may send them is set by the "reactions", "horn" and "shout" rate limiters.
 */
class LobbyReactionController extends Controller
{
    public function react(ReactRequest $request, Lobby $lobby): Response
    {
        return $this->broadcast($request, $lobby, emoji: $request->validated('emoji'));
    }

    public function horn(Request $request, Lobby $lobby): Response
    {
        // Every client maps the same number to the same horn sound.
        return $this->broadcast($request, $lobby, horn: random_int(0, 999));
    }

    public function shout(Request $request, Lobby $lobby): Response
    {
        return $this->broadcast($request, $lobby, shout: random_int(0, 999));
    }

    private function broadcast(Request $request, Lobby $lobby, ?string $emoji = null, ?int $horn = null, ?int $shout = null): Response
    {
        Gate::authorize('view', $lobby);
        // With up to 16 players, horns and shouts mid-match would drown the game.
        abort_if(($horn ?? $shout) !== null && $lobby->status === Lobby::STATUS_PLAYING, 409, 'Durante la partida no se puede pitar ni gritar.');

        // Reacting counts as activity, so a lobby that is only chatting is not closed as idle.
        $lobby->update(['last_activity_at' => now()]);
        event(new LobbyReacted($lobby->id, $request->user()->id, $emoji, $horn, $shout));

        return response()->noContent();
    }
}
