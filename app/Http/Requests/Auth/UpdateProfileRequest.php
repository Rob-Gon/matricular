<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            // Changing the password needs the current one; leaving all three empty keeps it.
            'current_password' => ['nullable', 'required_with:new_password,new_password_confirmation', 'current_password'],
            'new_password' => ['nullable', 'required_with:current_password', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'name.string' => 'El nombre debe ser una cadena de texto',
            'name.max' => 'El nombre no debe exceder los 255 caracteres',
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe ser una dirección de correo electrónico válida',
            'email.max' => 'El email no debe exceder los 255 caracteres',
            'email.unique' => 'El email ya está registrado',
            'current_password.required_with' => 'La contraseña actual es requerida',
            'current_password.current_password' => 'La contraseña actual es incorrecta',
            'new_password.required_with' => 'La nueva contraseña es requerida',
            'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres',
            'new_password.confirmed' => 'Las contraseñas no coinciden',
        ];
    }
}
