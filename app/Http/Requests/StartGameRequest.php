<?php

namespace App\Http\Requests;

use App\Enums\CountdownModes;
use App\Enums\GameDifficulties;
use App\Enums\GameModes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StartGameRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(GameModes::class)],
            'difficulty' => ['required', Rule::enum(GameDifficulties::class)],
            'countdown_time' => [
                'nullable',
                Rule::requiredIf($this->input('mode') === GameModes::CONTRARELOJ->value),
                Rule::enum(CountdownModes::class),
            ],
        ];
    }

    public function mode(): GameModes
    {
        return GameModes::from($this->validated('mode'));
    }

    public function difficulty(): GameDifficulties
    {
        return GameDifficulties::from($this->validated('difficulty'));
    }

    /** Only countdown games have a duration; the field is ignored otherwise. */
    public function countdown(): ?CountdownModes
    {
        return $this->mode() === GameModes::CONTRARELOJ
            ? CountdownModes::from($this->validated('countdown_time'))
            : null;
    }

    protected function failedValidation(Validator $validator): never
    {
        // The menu only displays flashed errors, not the validation bag.
        throw new HttpResponseException(
            redirect()->route('menu.view')->with('error', 'Error: datos de la partida incompletos o no válidos.')
        );
    }
}
