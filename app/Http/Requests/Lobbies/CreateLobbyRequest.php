<?php

namespace App\Http\Requests\Lobbies;

use App\Enums\LobbyFormats;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLobbyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', Rule::enum(LobbyFormats::class)],
            'password' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function lobbyFormat(): LobbyFormats
    {
        return LobbyFormats::from($this->validated('format'));
    }
}
