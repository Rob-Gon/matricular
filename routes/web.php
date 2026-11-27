<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\ScoreController;
use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app.home');
})->name('home.view');

Route::view('/creditos', 'app.credits')->name('credits.view');
Route::view('/como-se-juega', 'app.guide')->name('guide.view');

Route::get('/sitemap.xml', function () {
    return response()
        ->view('sitemap', ['routes' => ['home.view', 'guide.view', 'credits.view', 'register.view', 'login.view']])
        ->header('Content-Type', 'application/xml');
})->name('sitemap');

// Rutas para usuarios no autenticados
Route::middleware('guest')->group(function () {

    // Login
    Route::get('/login', [AuthController::class, 'loginView'])->name('login.view');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    // Registro
    Route::get('/register', [AuthController::class, 'registerView'])->name('register.view');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register');
});

// Rutas para usuarios autenticados y que ya han iniciado sesión
Route::middleware('auth')->group(function () {

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Perfil
    Route::get('/profile', [AuthController::class, 'profileView'])->name('profile.view');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::delete('/profile', [AuthController::class, 'deleteProfile'])->name('profile.delete');

    // Menú
    Route::get('/menu', function (Request $request) {
        $runs = Run::attempted()->whereBelongsTo($request->user());

        return view('main.menu', [
            'best' => (int) $runs->clone()->max('score'),
            'played' => $runs->count(),
        ]);
    })->name('menu.view');

    // Juego
    Route::prefix('game')->name('game.')->controller(GameController::class)->group(function () {
        Route::post('/', 'start')->name('start');
        Route::get('/setup', 'setup')->name('setup');
        Route::get('/{run}', 'show')->whereNumber('run')->name('show');
        Route::get('/{run}/summary', 'summary')->whereNumber('run')->name('summary');

        Route::middleware('throttle:game')->whereNumber('run')->group(function () {
            Route::post('/{run}/words', 'checkWord')->name('check-word');
            Route::post('/{run}/plate', 'changePlate')->name('change-plate');
            Route::post('/{run}/end', 'end')->name('end');
        });
    });

    // Puntuaciones
    Route::get('/scores', [ScoreController::class, 'index'])->name('scores.view');

    // Logros
    Route::get('/achievements', [AchievementController::class, 'index'])->name('achievements.view');

    // Multiplayer lobbies
    Route::prefix('lobbies')->name('lobbies.')->controller(LobbyController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
        // Throttled: the code and the optional password must not be guessable by brute force.
        Route::post('/join', 'join')->middleware('throttle:10,1')->name('join');
        Route::get('/{lobby}', 'show')->name('show');
        Route::post('/{lobby}/leave', 'leave')->name('leave');

        Route::middleware('throttle:game')->group(function () {
            Route::post('/{lobby}/ready', 'ready')->name('ready');
            Route::post('/{lobby}/color', 'color')->name('color');
            Route::put('/{lobby}/rules', 'rules')->name('rules');
            Route::post('/{lobby}/kick', 'kick')->name('kick');
        });
    });
});
