<?php

namespace App\Http\Requests\Lobbies;

use App\Models\Lobby;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', Rule::in(array_keys(Lobby::REACTIONS))],
        ];
    }
}
