<?php

namespace App\Http\Requests\Lobbies;

use Illuminate\Foundation\Http\FormRequest;

class JoinLobbyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Players copy codes with the space of the plate ("4821 KTZ") or in lower case.
        $this->merge(['code' => strtoupper(str_replace([' ', '-'], '', (string) $this->input('code')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[0-9]{4}[A-Z]{3}$/'],
            'join_password' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => 'El código de sala tiene cuatro números y tres letras, como una matrícula.'];
    }
}
