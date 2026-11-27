<?php

namespace App\Http\Controllers;

use App\Enums\CountdownModes;
use App\Enums\GameModes;
use App\Enums\LetterSets;
use App\Models\Run;
use App\Models\RunWord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScoreController extends Controller
{
    /**
     * Rankings filtered by the query string, plus the player's latest runs.
     */
    public function index(Request $request): View
    {
        // Filters come from links and a GET form: invalid values are dropped instead of redirecting.
        $filters = Validator::make($request->query(), [
            'mode' => ['nullable', Rule::enum(GameModes::class)],
            'letter_set' => ['nullable', Rule::enum(LetterSets::class)],
            'ordered' => ['nullable', 'in:0,1'],
            'time' => ['nullable', Rule::enum(CountdownModes::class)],
        ])->valid();

        if (($filters['mode'] ?? null) !== GameModes::CONTRARELOJ->value) {
            unset($filters['time']);
        }

        $recentRuns = Run::attempted()
            ->whereBelongsTo($request->user())
            ->latest()
            ->limit(10)
            ->get();

        return view('main.scores', [
            'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
            'topScores' => $this->topScoresQuery($filters)->get(),
            'recentRuns' => $recentRuns,
        ]);
    }

    /**
     * Top 10 players by their best score, counting only runs with at least one attempted word.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    private function topScoresQuery(array $filters): Builder
    {
        return User::select('users.id', 'users.name')
            ->selectRaw('MAX(runs.score) as highest_score')
            ->selectRaw('COUNT(runs.id) as total_runs')
            ->join('runs', 'users.id', '=', 'runs.user_id')
            ->whereNull('runs.deleted_at')
            ->whereExists(fn ($query) => $query->select('id')->from('run_words')
                ->whereColumn('run_words.run_id', 'runs.id')
                ->where('run_words.kind', RunWord::KIND_WORD))
            ->when($filters['mode'] ?? null, fn (Builder $query, string $mode) => $query->where('runs.mode', $mode))
            ->when($filters['letter_set'] ?? null, fn (Builder $query, string $set) => $query->where('runs.letter_set', $set))
            ->when(isset($filters['ordered']) && $filters['ordered'] !== '', fn (Builder $query) => $query->where('runs.ordered', $filters['ordered'] === '1'))
            ->when($filters['time'] ?? null, fn (Builder $query, string $time) => $query->where('runs.countdown_time', $time))
            ->groupBy('users.id', 'users.name')
            ->orderBy('highest_score', 'desc')
            ->orderBy('users.name', 'asc')
            ->limit(10);
    }
}
