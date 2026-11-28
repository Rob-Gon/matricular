<?php

namespace App\Providers;

use App\Models\Lobby;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // About two submissions per second: enough for fast typing, blocks scripted flooding.
        RateLimiter::for('game', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('reactions', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        // Horns and shouts: one of each every few seconds per player (each limiter keeps its own count).
        RateLimiter::for('horn', fn (Request $request) => Limit::perSecond(1, Lobby::SOUND_COOLDOWN_SECONDS)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('shout', fn (Request $request) => Limit::perSecond(1, Lobby::SOUND_COOLDOWN_SECONDS)->by($request->user()?->id ?: $request->ip()));
    }
}
