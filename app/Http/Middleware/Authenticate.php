<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    /**
     * Redirige al usuario a la página de inicio si no ha iniciado sesión
     *
     * @param  Request  $request
     * @param  string  ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login.view')->with('error', 'Debes iniciar sesión para acceder a esta página.');
        }

        return $next($request);
    }

    /**
     * Redirige al usuario a la página de inicio si no ha iniciado sesión
     */
    protected function redirectTo(Request $request): ?string
    {
        return route('login.view');
    }
}
