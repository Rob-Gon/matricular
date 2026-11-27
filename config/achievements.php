<?php

use App\Achievements\FirstGame;
use App\Achievements\HardHighScore;
use App\Achievements\HighScore;
use App\Achievements\LongWord;
use App\Achievements\Palindrome;
use App\Achievements\QuickSuccession;
use App\Achievements\TouchGrass;

/*
| Achievement catalogue. The order is the display order. `key` is the stable identifier stored
| in the database; `class` holds the unlock condition (null for the platinum meta achievement).
| After changing this file run: php artisan db:seed --class=AchievementsSeeder
*/

return [
    [
        'key' => 'first_game',
        'name' => 'Pasito a pasito...',
        'description' => 'Termina tu primera partida con al menos una palabra',
        'icon' => 'flag',
        'hidden' => false,
        'class' => FirstGame::class,
    ],
    [
        'key' => 'long_word',
        'name' => 'Palabra kilométrica',
        'description' => 'Acierta una palabra de 12 letras o más',
        'icon' => 'road',
        'hidden' => false,
        'class' => LongWord::class,
    ],
    [
        'key' => 'palindrome',
        'name' => '¡Palíndromo!',
        'description' => 'Acierta una palabra que se lea igual al revés',
        'icon' => 'arrows',
        'hidden' => true,
        'class' => Palindrome::class,
    ],
    [
        'key' => 'high_score',
        'name' => 'Matriculando fuerte',
        'description' => 'Consigue 500 puntos en una sola partida',
        'icon' => 'speedometer',
        'hidden' => false,
        'class' => HighScore::class,
    ],
    [
        'key' => 'hard_high_score',
        'name' => 'Matricúlame esta',
        'description' => 'Consigue 250 puntos en una sola partida en modo difícil',
        'icon' => 'speedometer',
        'hidden' => false,
        'class' => HardHighScore::class,
    ],
    [
        'key' => 'quick_succession',
        'name' => 'El menos picado',
        'description' => 'Juega 5 partidas en menos de 10 minutos',
        'icon' => 'clock',
        'hidden' => false,
        'class' => QuickSuccession::class,
    ],
    [
        'key' => 'touch_grass',
        'name' => 'Deberías tocar pasto',
        'description' => 'Juega 25 partidas en un día',
        'icon' => 'grass',
        'hidden' => false,
        'class' => TouchGrass::class,
    ],
    [
        'key' => 'platinum',
        'name' => 'Platino Compulsivo',
        'description' => 'Consigue todos los logros',
        'icon' => 'trophy',
        'hidden' => false,
        'class' => null,
    ],
];
