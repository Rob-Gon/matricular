<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitWordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'word' => ['required', 'string', 'min:3', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'word.required' => 'La palabra es requerida.',
            'word.min' => 'La palabra debe tener al menos 3 caracteres.',
            'word.max' => 'La palabra no debe exceder los 50 caracteres.',
        ];
    }
}
