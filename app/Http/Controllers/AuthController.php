<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function registerView()
    {
        return view('app.register');
    }

    public function register(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ];

        $messages = [
            'name.required' => 'El nombre es requerido',
            'name.string' => 'El nombre debe ser una cadena de texto',
            'name.max' => 'El nombre no debe exceder los 255 caracteres',
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe ser una dirección de correo electrónico válida',
            'email.max' => 'El email no debe exceder los 255 caracteres',
            'email.unique' => 'El email ya está registrado',
            'password.required' => 'La contraseña es requerida',
            'password.string' => 'La contraseña debe ser una cadena de texto',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->route('register.view')->withErrors($validator)->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Registro exitoso. Por favor, inicia sesión.',
                'redirect' => route('login.view')
            ]);
        }

        return redirect()->route('login.view')->with('success', 'Registro exitoso. Por favor, inicia sesión.');
    }

    public function loginView()
    {
        return view('app.login');
    }

    public function login(Request $request)
    {
        $rules = [
            'email' => 'required|email',
            'password' => 'required',
        ];

        $messages = [
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe ser una dirección de correo electrónico válida',
            'password.required' => 'La contraseña es requerida',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->route('login.view')->withErrors($validator)->withInput();
        }

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            // Establecer tiempo inicial de última actividad
            $request->session()->put('last_activity', time());
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sesión iniciada correctamente',
                    'redirect' => route('menu.view')
                ]);
            }
            
            return redirect()->route('menu.view');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales proporcionadas no coinciden con nuestros registros.'
            ], 401);
        }

        return redirect()->route('login.view')->with('error', 'Las credenciales proporcionadas no coinciden con nuestros registros.');
    }

    public function logout(Request $request)
    {
        // Limpiar el remember token si existe
        if (Auth::check() && Auth::user()->remember_token) {
            Auth::user()->update(['remember_token' => null]);
        }
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada correctamente',
                'redirect' => route('login.view')
            ]);
        }
        
        return redirect()->route('login.view')->with('success', 'Sesión cerrada correctamente.');
    }

    public function profileView()
    {
        return view('app.profile');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ];

        $messages = [
            'name.required' => 'El nombre es requerido',
            'name.string' => 'El nombre debe ser una cadena de texto',
            'name.max' => 'El nombre no debe exceder los 255 caracteres',
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe ser una dirección de correo electrónico válida',
            'email.max' => 'El email no debe exceder los 255 caracteres',
            'email.unique' => 'El email ya está registrado',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->route('profile.view')->withErrors($validator)->withInput();
        }

        if ($request->filled('current_password') || $request->filled('new_password') || $request->filled('new_password_confirmation')) {
            $rules = [
                'current_password' => 'required',
                'new_password' => 'required|string|min:8|confirmed',
            ];

            $messages = [
                'current_password.required' => 'La contraseña actual es requerida',
                'new_password.required' => 'La nueva contraseña es requerida',
                'new_password.string' => 'La nueva contraseña debe ser una cadena de texto',
                'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres',
                'new_password.confirmed' => 'Las contraseñas no coinciden',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);
            
            if($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error de validación de contraseña',
                        'errors' => $validator->errors()
                    ], 422);
                }
                return redirect()->route('profile.view')->withErrors($validator)->withInput();
            }
            
            if (!Hash::check($request->current_password, $user->password)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La contraseña actual es incorrecta'
                    ], 422);
                }
                return redirect()->route('profile.view')->with('error', 'La contraseña actual es incorrecta.');
            }

            $user->password = Hash::make($request->new_password);
        }
        
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado correctamente'
            ]);
        }

        return redirect()->route('profile.view')->with('success', 'Perfil actualizado correctamente.');
    }

    public function deleteProfile(Request $request)
    {
        $user = Auth::user();
        $user->delete();
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Cuenta eliminada correctamente',
                'redirect' => route('login.view')
            ]);
        }
        
        return redirect()->route('login.view')->with('success', 'Cuenta eliminada correctamente.');
    }
}
