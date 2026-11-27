<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ScoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app.home');
})->name('home.view');

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
    Route::get('/menu', function () {
        return view('main.menu');
    })->name('menu.view');

    // Juego
    Route::prefix('game')->name('game.')->controller(GameController::class)->group(function () {
        Route::post('/', 'start')->name('start');
        Route::get('/countdown/modes', 'countdownModesView')->name('countdown-modes');
        Route::get('/difficulty/modes', 'difficultyModesView')->name('difficulty-modes');
        Route::get('/{run}', 'show')->whereNumber('run')->name('show');

        Route::middleware('throttle:game')->whereNumber('run')->group(function () {
            Route::post('/{run}/words', 'checkWord')->name('check-word');
            Route::post('/{run}/plate', 'changePlate')->name('change-plate');
            Route::post('/{run}/end', 'end')->name('end');
        });
    });

    // Puntuaciones
    Route::get('/scores', [ScoreController::class, 'index'])->name('scores.view');
    Route::get('/scores/filter', [ScoreController::class, 'filter'])->name('scores.filter');

    // Logros
    Route::get('/achievements', function () {
        return view('main.achievements');
    })->name('achievements.view');
});
