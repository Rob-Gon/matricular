<?php

use Illuminate\Support\Facades\Schedule;

// Words learned from the RAE API change how many solutions each plate has.
Schedule::command('dictionary:plates')->weekly()->withoutOverlapping();
Schedule::command('lobbies:close-idle')->everyFiveMinutes()->withoutOverlapping();
