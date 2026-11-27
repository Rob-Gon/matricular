<?php

namespace App\Console\Commands;

use App\Services\LobbyManager;
use Illuminate\Console\Command;

class CloseIdleLobbies extends Command
{
    protected $signature = 'lobbies:close-idle';

    protected $description = 'Close lobbies with no activity for 30 minutes (matches in progress are kept)';

    public function handle(LobbyManager $lobbies): int
    {
        $this->info('Closed '.$lobbies->closeIdle().' idle lobbies.');

        return self::SUCCESS;
    }
}
