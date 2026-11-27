<?php

namespace App\Console\Commands;

use App\Services\LobbyManager;
use App\Services\RaceManager;
use Illuminate\Console\Command;

class CloseIdleLobbies extends Command
{
    protected $signature = 'lobbies:close-idle';

    protected $description = 'Finish races whose clock ran out and close lobbies with no activity for 30 minutes';

    public function handle(LobbyManager $lobbies, RaceManager $races): int
    {
        $this->info('Finished '.$races->finishOverdue().' races.');
        $this->info('Closed '.$lobbies->closeIdle().' idle lobbies.');

        return self::SUCCESS;
    }
}
