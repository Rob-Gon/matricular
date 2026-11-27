<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\DeleteProfileRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function registerView(): View
    {
        return view('app.register');
    }

    public function register(RegisterRequest $request): JsonResponse|RedirectResponse
    {
        // The "hashed" cast on User hashes the password.
        User::create($request->validated());

        return $this->respond($request, 'Registro exitoso. Por favor, inicia sesión.', route('login.view'));
    }

    public function loginView(): View
    {
        return view('app.login');
    }

    public function login(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return $this->respond($request, 'Sesión iniciada correctamente', route('menu.view'), flash: false);
    }

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        // Logging out also rotates the remember token, so "remember me" cookies stop working.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->respond($request, 'Sesión cerrada correctamente', route('login.view'));
    }

    public function profileView(): View
    {
        return view('app.profile');
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->name = $request->validated('name');
        $user->email = $request->validated('email');

        if ($request->filled('new_password')) {
            $user->password = $request->validated('new_password');
        }

        $user->save();

        return $this->respond($request, 'Perfil actualizado correctamente', route('profile.view'));
    }

    public function deleteProfile(DeleteProfileRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->respond($request, 'Cuenta eliminada correctamente', route('login.view'));
    }

    /**
     * The auth forms submit with fetch (JSON); plain form posts get a redirect with a flash message.
     */
    private function respond(Request $request, string $message, string $redirect, bool $flash = true): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'redirect' => $redirect]);
        }

        $response = redirect()->to($redirect);

        return $flash ? $response->with('success', $message) : $response;
    }
}
