<?php

namespace App\Http\Controllers;

use App\Enums\CountdownModes;
use App\Enums\GameModes;
use App\Enums\LetterSets;
use App\Enums\LobbyFormats;
use App\Models\Run;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScoreController extends Controller
{
    public const TAB_SOLO = 'solo';

    public const TAB_MULTI = 'multi';

    /** Rankings are listed by Spanish calendar day. */
    public const DAY_TIMEZONE = 'Europe/Madrid';

    private const TOP = 10;

    /**
     * Solo and multiplayer rankings (kept apart): the best games ever and today, the player's best
     * games and their latest ones. A game ranks by its points; filters narrow it down.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === self::TAB_MULTI ? self::TAB_MULTI : self::TAB_SOLO;
        $filters = $this->filters($request, $tab);
        $ranked = fn (): Builder => $tab === self::TAB_MULTI ? $this->multiplayerRuns($filters) : $this->soloRuns($filters);
        $user = $request->user();

        return view('main.scores', [
            'tab' => $tab,
            'filters' => $filters,
            'topRuns' => $ranked()->limit(self::TOP)->get(),
            'todayRuns' => $ranked()->where('runs.created_at', '>=', now(self::DAY_TIMEZONE)->startOfDay()->utc())->limit(self::TOP)->get(),
            'bestRuns' => $ranked()->whereBelongsTo($user)->limit(self::TOP)->get(),
            'recentRuns' => $this->runs($tab)->attempted()->whereBelongsTo($user)->latest()->latest('id')->limit(self::TOP)->get(),
        ]);
    }

    /**
     * Filters come from links and a GET form: invalid values are dropped instead of redirecting.
     *
     * @return array<string, string>
     */
    private function filters(Request $request, string $tab): array
    {
        $rules = [
            'letter_set' => ['nullable', Rule::enum(LetterSets::class)],
            'ordered' => ['nullable', 'in:0,1'],
            ...($tab === self::TAB_MULTI
                ? ['format' => ['nullable', Rule::enum(LobbyFormats::class)]]
                : ['mode' => ['nullable', Rule::enum(GameModes::class)], 'time' => ['nullable', Rule::enum(CountdownModes::class)]]),
        ];
        // valid() keeps keys without rules too ("tab"), so only the filters are taken.
        $filters = Arr::only(Validator::make($request->query(), $rules)->valid(), array_keys($rules));

        if (($filters['mode'] ?? null) !== GameModes::CONTRARELOJ->value) {
            unset($filters['time']);
        }

        return array_filter($filters, fn ($value) => is_string($value) && $value !== '');
    }

    /**
     * Solo or multiplayer runs (the "solo" scope hides multiplayer ones), with what the tables show.
     *
     * @return Builder<Run>
     */
    private function runs(string $tab): Builder
    {
        return $tab === self::TAB_MULTI
            ? Run::withoutGlobalScope(Run::SOLO_SCOPE)->whereNotNull('runs.lobby_match_id')->with(['user:id,name', 'lobbyMatch:id,format'])
            : Run::query()->with('user:id,name');
    }

    /**
     * Ranked games: at least one word attempted, best score first (the earlier game wins a tie).
     *
     * @param  Builder<Run>  $query
     * @param  array<string, string>  $filters
     * @return Builder<Run>
     */
    private function ranked(Builder $query, array $filters): Builder
    {
        return $query->attempted()
            ->when($filters['letter_set'] ?? null, fn (Builder $query, string $set) => $query->where('runs.letter_set', $set))
            ->when(isset($filters['ordered']), fn (Builder $query) => $query->where('runs.ordered', $filters['ordered'] === '1'))
            ->orderByDesc('runs.score')
            ->orderBy('runs.created_at')
            ->orderBy('runs.id');
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<Run>
     */
    private function soloRuns(array $filters): Builder
    {
        return $this->ranked($this->runs(self::TAB_SOLO), $filters)
            ->when($filters['mode'] ?? null, fn (Builder $query, string $mode) => $query->where('runs.mode', $mode))
            // Custom countdowns (up to an hour) only rank against each other, under their own filter.
            ->when(
                $filters['time'] ?? null,
                fn (Builder $query, string $time) => $query->where('runs.countdown_time', $time),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->whereNull('runs.countdown_time')
                    ->orWhere('runs.countdown_time', '!=', CountdownModes::PERSONALIZADO->value)),
            );
    }

    /**
     * Multiplayer games rank once their match is over, so a race still running shows no half scores.
     *
     * @param  array<string, string>  $filters
     * @return Builder<Run>
     */
    private function multiplayerRuns(array $filters): Builder
    {
        return $this->ranked($this->runs(self::TAB_MULTI), $filters)
            ->whereHas('lobbyMatch', fn (Builder $query) => $query
                ->whereNotNull('finished_at')
                ->when($filters['format'] ?? null, fn (Builder $query, string $format) => $query->where('format', $format)));
    }
}
