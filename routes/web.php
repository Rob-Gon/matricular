<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ScoreController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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
    Route::post('/register', [AuthController::class, 'register'])->name('register');
});

// Rutas para usuarios autenticados y que ya han iniciado sesión
Route::middleware('auth')->group(function () {

    // Extender la sesión del usuario
    Route::post('/extend-session', function () {
        // Actualizar tiempo de última actividad
        session(['last_activity' => time()]);

        // Regenerar remember token si es necesario
        if (Auth::check() && ! Auth::user()->remember_token) {
            Auth::user()->update(['remember_token' => Str::random(60)]);
        }

        return response()->json(['success' => true]);
    })->name('extend-session');

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
    Route::prefix('game')->group(function () {
        Route::post('/', [GameController::class, 'gameView'])->name('game.view');
        Route::get('/countdown/modes', [GameController::class, 'countdownModesView'])->name('game.countdown-modes');
        Route::get('/difficulty/modes', [GameController::class, 'difficultyModesView'])->name('game.difficulty-modes');
        Route::post('/infinite', [GameController::class, 'infiniteView'])->name('game.infinite');
        Route::post('/check-word', [GameController::class, 'checkWord'])->name('game.check-word');
        Route::post('/change-plate', [GameController::class, 'changePlate'])->name('game.change-plate');
        Route::post('/reset-run', [GameController::class, 'resetRun'])->name('game.reset-run');
    });

    // Puntuaciones
    Route::get('/scores', [ScoreController::class, 'index'])->name('scores.view');
    Route::get('/scores/filter', [ScoreController::class, 'filter'])->name('scores.filter');

    // Logros
    Route::get('/achievements', function () {
        return view('main.achievements');
    })->name('achievements.view');
});
