<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verifica si el usuario está autenticado
        if (Auth::check()) {
            // Obtiene la última actividad del usuario desde la sesión
            $lastActivity = Session::get('last_activity');
            $currentTime = time();

            // 12 horas en segundos
            $sessionTimeout = 43200;

            // Si desde la última actividad han pasado más de 12 horas, se desloguea el usuario
            if ($lastActivity && ($currentTime - $lastActivity) > $sessionTimeout) {
                // Limpiar el remember token si existe
                if (Auth::user()->remember_token) {
                    Auth::user()->update(['remember_token' => null]);
                }

                Auth::logout();
                Session::flush();
                Session::flash('session_expired', 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.');

                return redirect()->route('login.view');
            }

            // Actualiza la última actividad
            Session::put('last_activity', $currentTime);
        }

        return $next($request);
    }
}
