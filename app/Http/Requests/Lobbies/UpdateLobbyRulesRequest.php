<?php

namespace App\Http\Requests\Lobbies;

use App\Enums\CountdownModes;
use App\Enums\LetterSets;
use App\Enums\LobbyFormats;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The host's rules for the next match; limits agreed with the owner (.claude/rules/game.md). */
class UpdateLobbyRulesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $format = $this->input('format');

        return [
            'format' => ['required', Rule::enum(LobbyFormats::class)],
            'letter_set' => ['required', Rule::enum(LetterSets::class)],
            'ordered' => ['required', 'boolean'],
            'tie_resolution' => ['required', 'in:shared,tiebreak'],
            'countdown_time' => [Rule::requiredIf($format === LobbyFormats::RACE->value), 'nullable', Rule::enum(CountdownModes::class)],
            'custom_minutes' => [
                Rule::requiredIf($format === LobbyFormats::RACE->value && $this->input('countdown_time') === CountdownModes::PERSONALIZADO->value),
                'nullable', 'integer', 'between:1,'.CountdownModes::MAX_CUSTOM_MINUTES,
            ],
            'rounds' => [Rule::requiredIf($format === LobbyFormats::ROUNDS->value), 'nullable', 'integer', 'between:1,30'],
            'round_seconds' => [Rule::requiredIf($format === LobbyFormats::ROUNDS->value), 'nullable', 'integer', 'between:10,300'],
            'plates' => [Rule::requiredIf($format === LobbyFormats::FIRST->value), 'nullable', 'integer', 'between:1,50'],
            'plate_seconds' => [Rule::requiredIf($format === LobbyFormats::FIRST->value), 'nullable', 'integer', 'between:10,120'],
            'tie_window_ms' => [Rule::requiredIf($format === LobbyFormats::FIRST->value), 'nullable', 'in:0,150,300'],
        ];
    }

    public function lobbyFormat(): LobbyFormats
    {
        return LobbyFormats::from($this->validated('format'));
    }

    /**
     * Only the settings that apply to the chosen format are stored.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $common = [
            'letter_set' => $this->validated('letter_set'),
            'ordered' => $this->boolean('ordered'),
            'tie_resolution' => $this->validated('tie_resolution'),
        ];

        return $common + match ($this->lobbyFormat()) {
            LobbyFormats::RACE => $this->raceLength(),
            LobbyFormats::ROUNDS => ['rounds' => (int) $this->validated('rounds'), 'round_seconds' => (int) $this->validated('round_seconds')],
            LobbyFormats::FIRST => [
                'plates' => (int) $this->validated('plates'),
                'plate_seconds' => (int) $this->validated('plate_seconds'),
                'tie_window_ms' => (int) $this->validated('tie_window_ms'),
            ],
        };
    }

    /**
     * @return array{countdown_time: string, countdown_seconds: int}
     */
    private function raceLength(): array
    {
        $countdown = CountdownModes::from($this->validated('countdown_time'));

        return [
            'countdown_time' => $countdown->value,
            'countdown_seconds' => $countdown->isCustom() ? (int) $this->validated('custom_minutes') * 60 : (int) $countdown->seconds(),
        ];
    }
}
