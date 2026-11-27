<?php

namespace App\Http\Controllers;

use App\Enums\CountdownModes;
use App\Enums\GameModes;
use App\Enums\LetterSets;
use App\Models\Run;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScoreController extends Controller
{
    /**
     * Muestra la página de puntuaciones con las puntuaciones más altas para cada usuario
     *
     * @return View
     */
    public function index()
    {
        // Obtener la puntuación más alta para cada usuario
        $topScores = $this->topScoresQuery()->get();

        // Obtener las puntuaciones más recientes del usuario actual
        $recentTopScores = Run::with('user')
            ->attempted()
            ->where('user_id', auth()->user()->id)
            ->orderBy('created_at', 'desc')
            ->withTrashed()
            ->limit(10)
            ->get();

        return view('main.scores', compact('topScores', 'recentTopScores'));
    }

    /**
     * Top scores filtered by mode, settings and countdown time (AJAX)
     */
    public function filter(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'mode' => ['nullable', Rule::enum(GameModes::class)],
            'letter_set' => ['nullable', Rule::enum(LetterSets::class)],
            'ordered' => ['nullable', 'boolean'],
            'time' => ['nullable', Rule::enum(CountdownModes::class)],
        ]);

        $query = $this->topScoresQuery();

        if (isset($filters['mode'])) {
            $query->where('runs.mode', $filters['mode']);
        }
        if (isset($filters['letter_set'])) {
            $query->where('runs.letter_set', $filters['letter_set']);
        }
        if (isset($filters['ordered'])) {
            $query->where('runs.ordered', $request->boolean('ordered'));
        }
        if (($filters['mode'] ?? null) === GameModes::CONTRARELOJ->value && isset($filters['time'])) {
            $query->where('runs.countdown_time', $filters['time']);
        }

        return response()->json(['topScores' => $query->get()]);
    }

    /**
     * Top 10 de usuarios por su mejor puntuación, contando solo runs con al menos una palabra intentada
     *
     * @return Builder<User>
     */
    private function topScoresQuery(): Builder
    {
        return User::select('users.id', 'users.name')
            ->selectRaw('MAX(runs.score) as highest_score')
            ->selectRaw('COUNT(runs.id) as total_runs')
            ->join('runs', 'users.id', '=', 'runs.user_id')
            ->where(fn (Builder $query) => $query
                ->whereJsonLength('runs.words_correct', '>', 0)
                ->orWhereJsonLength('runs.words_error', '>', 0))
            ->groupBy('users.id', 'users.name')
            ->orderBy('highest_score', 'desc')
            ->orderBy('users.name', 'asc')
            ->limit(10);
    }
}
