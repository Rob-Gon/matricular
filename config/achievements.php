<?php

use App\Achievements\AbroHilo;
use App\Achievements\AdjectivesInARun;
use App\Achievements\FirstGame;
use App\Achievements\HardHighScore;
use App\Achievements\HighScore;
use App\Achievements\LongWord;
use App\Achievements\Monovocalic;
use App\Achievements\NegativeScore;
use App\Achievements\NounsInARun;
use App\Achievements\Palindrome;
use App\Achievements\Panvocalic;
use App\Achievements\QuickSuccession;
use App\Achievements\TouchGrass;
use App\Achievements\TrapElectroencefalografista;
use App\Achievements\TrapPneumono;
use App\Achievements\VerbsInARun;

/*
| Achievement catalogue. The order is the display order. `key` is the stable identifier stored
| in the database; `class` holds the unlock condition (null for the platinum meta achievement);
| `icon` picks the sticker artwork (components/sticker). Names and descriptions are the owner's.
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
        'key' => 'high_score',
        'name' => 'Matriculando fuerte',
        'description' => 'Consigue 500 puntos en una partida contrarreloj',
        'icon' => 'speedometer',
        'hidden' => false,
        'class' => HighScore::class,
    ],
    [
        'key' => 'hard_high_score',
        'name' => 'Matricúlame esta',
        'description' => 'Consigue 250 puntos en una sola partida con todas las letras y en orden',
        'icon' => 'plate',
        'hidden' => false,
        'class' => HardHighScore::class,
    ],
    [
        'key' => 'nouns',
        'name' => 'Sustantívame esta',
        'description' => 'Acierta 10 sustantivos en una misma partida',
        'icon' => 'noun',
        'hidden' => false,
        'class' => NounsInARun::class,
    ],
    [
        'key' => 'adjectives',
        'name' => 'Adjetívame esta',
        'description' => 'Acierta 10 adjetivos en una misma partida',
        'icon' => 'adjective',
        'hidden' => false,
        'class' => AdjectivesInARun::class,
    ],
    [
        'key' => 'verbs',
        'name' => 'Verbéame esta',
        'description' => 'Acierta 10 verbos en una misma partida',
        'icon' => 'verb',
        'hidden' => false,
        'class' => VerbsInARun::class,
    ],
    [
        'key' => 'panvocalic',
        'name' => 'Murciégalo',
        'description' => 'Acierta una palabra con las cinco vocales',
        'icon' => 'bat',
        'hidden' => false,
        'class' => Panvocalic::class,
    ],
    [
        'key' => 'monovocalic',
        'name' => 'Abracadabra',
        'description' => 'Acierta una palabra con una sola vocal repetida al menos cuatro veces. La técnica mítica de El Menor',
        'icon' => 'wand',
        'hidden' => false,
        'class' => Monovocalic::class,
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
        'key' => 'palindrome',
        'name' => 'Se es o no se es',
        'description' => 'Acierta una palabra que se lea igual al revés',
        'icon' => 'mirror',
        'hidden' => true,
        'class' => Palindrome::class,
    ],
    [
        'key' => 'abro_hilo',
        'name' => 'Abro hilo',
        'description' => 'Acierta seguidos un sustantivo, un adjetivo y un verbo, en cualquier orden',
        'icon' => 'thread',
        'hidden' => true,
        'class' => AbroHilo::class,
    ],
    [
        'key' => 'negative_score',
        'name' => 'Más malo que pegarle a un padre',
        'description' => 'Termina una partida con puntuación negativa',
        'icon' => 'pothole',
        'hidden' => true,
        'class' => NegativeScore::class,
    ],
    [
        'key' => 'trap_pneumono',
        'name' => 'Buen intento, perro',
        'description' => 'Prueba suerte con «pneumonoultramicroscopicsilicovolcanoconiosis»',
        'icon' => 'dog',
        'hidden' => true,
        'class' => TrapPneumono::class,
    ],
    [
        'key' => 'trap_electro',
        'name' => 'Ratita de la RAE',
        'description' => 'Prueba suerte con «electroencefalografista»',
        'icon' => 'mouse',
        'hidden' => true,
        'class' => TrapElectroencefalografista::class,
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
