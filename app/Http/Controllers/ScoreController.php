<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
     * Devuelve las puntuaciones filtradas por modo, dificultad y tiempo (AJAX)
     */
    public function filter(Request $request)
    {
        $mode = $request->input('mode');
        $difficulty = $request->input('difficulty');
        $time = $request->input('time');

        $query = $this->topScoresQuery();

        if ($mode) {
            $query->where('runs.mode', $mode);
        }
        if ($difficulty) {
            $query->where('runs.difficulty', $difficulty);
        }
        if ($mode === 'Contrareloj' && $time) {
            $query->where('runs.countdown_time', $time);
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
        return User::select('users.id', 'users.name', 'users.email')
            ->selectRaw('MAX(runs.score) as highest_score')
            ->selectRaw('COUNT(runs.id) as total_runs')
            ->join('runs', 'users.id', '=', 'runs.user_id')
            ->where(fn (Builder $query) => $query
                ->whereJsonLength('runs.words_correct', '>', 0)
                ->orWhereJsonLength('runs.words_error', '>', 0))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('highest_score', 'desc')
            ->orderBy('users.name', 'asc')
            ->limit(10);
    }
}
