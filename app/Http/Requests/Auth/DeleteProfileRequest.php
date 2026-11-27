<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class DeleteProfileRequest extends FormRequest
{
    /** Its own bag, so the profile page can reopen the confirmation dialog instead of flagging the profile form. */
    protected $errorBag = 'deleteAccount';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Introduce tu contraseña para eliminar la cuenta',
            'password.current_password' => 'La contraseña es incorrecta',
        ];
    }
}
