<?php

namespace App\Policies;

use App\Models\Lobby;
use App\Models\User;

class LobbyPolicy
{
    /** Only players inside the room see it and hear its channel. */
    public function view(User $user, Lobby $lobby): bool
    {
        return $lobby->hasPlayer($user);
    }

    /** The host changes the rules and kicks players. */
    public function manage(User $user, Lobby $lobby): bool
    {
        return $lobby->host_id === $user->id;
    }
}
