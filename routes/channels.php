<?php

use App\Models\Lobby;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// Presence channel of a lobby (by id, the code is the URL key): only its players join, listed by id and name.
Broadcast::channel('lobby.{lobbyId}', function (User $user, int $lobbyId) {
    $lobby = Lobby::find($lobbyId);

    return $lobby !== null && $user->can('view', $lobby) ? ['id' => $user->id, 'name' => $user->name] : false;
});
