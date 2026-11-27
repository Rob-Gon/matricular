<?php

namespace App\Http\Requests;

use App\Enums\CountdownModes;
use App\Enums\GameModes;
use App\Enums\LetterSets;
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
            'letter_set' => ['required', Rule::enum(LetterSets::class)],
            'ordered' => ['required', 'boolean'],
            'countdown_time' => [
                'nullable',
                Rule::requiredIf($this->input('mode') === GameModes::CONTRARELOJ->value),
                Rule::enum(CountdownModes::class),
            ],
            'custom_minutes' => [
                'nullable',
                Rule::requiredIf($this->input('mode') === GameModes::CONTRARELOJ->value
                    && $this->input('countdown_time') === CountdownModes::PERSONALIZADO->value),
                'integer',
                'between:1,'.CountdownModes::MAX_CUSTOM_MINUTES,
            ],
        ];
    }

    public function mode(): GameModes
    {
        return GameModes::from($this->validated('mode'));
    }

    public function letterSet(): LetterSets
    {
        return LetterSets::from($this->validated('letter_set'));
    }

    public function ordered(): bool
    {
        return $this->boolean('ordered');
    }

    /** Only countdown games have a duration; the field is ignored otherwise. */
    public function countdown(): ?CountdownModes
    {
        return $this->mode() === GameModes::CONTRARELOJ
            ? CountdownModes::from($this->validated('countdown_time'))
            : null;
    }

    /** Length of the countdown in seconds: the preset one, or the minutes the player chose. */
    public function countdownSeconds(): ?int
    {
        $countdown = $this->countdown();

        return $countdown?->isCustom() ? (int) $this->validated('custom_minutes') * 60 : $countdown?->seconds();
    }

    protected function failedValidation(Validator $validator): never
    {
        // Every field but the custom minutes is a preset choice, so a single flashed error is enough.
        $message = $validator->errors()->has('custom_minutes')
            ? 'Elige una duración personalizada de 1 a '.CountdownModes::MAX_CUSTOM_MINUTES.' minutos.'
            : 'Error: datos de la partida incompletos o no válidos.';

        throw new HttpResponseException(
            redirect()->route('game.setup')->with('error', $message)
        );
    }
}
