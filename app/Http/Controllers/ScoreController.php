<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Models\User;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    /**
     * Muestra la página de puntuaciones con las puntuaciones más altas para cada usuario
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Obtener la puntuación más alta para cada usuario
        $topScores = User::select('users.id', 'users.name', 'users.email')
            ->selectRaw('MAX(runs.score) as highest_score')
            ->selectRaw('COUNT(runs.id) as total_runs')
            ->join('runs', 'users.id', '=', 'runs.user_id')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('highest_score', 'desc')
            ->orderBy('users.name', 'asc')
            ->limit(10)
            ->get();

        // Obtener las puntuaciones más recientes del usuario actual
        $recentTopScores = Run::with('user')
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

        $query = User::select('users.id', 'users.name', 'users.email')
            ->selectRaw('MAX(runs.score) as highest_score')
            ->selectRaw('COUNT(runs.id) as total_runs')
            ->join('runs', 'users.id', '=', 'runs.user_id');

        if ($mode) {
            $query->where('runs.mode', $mode);
        }
        if ($difficulty) {
            $query->where('runs.difficulty', $difficulty);
        }
        if ($mode === 'Contrareloj' && $time) {
            $query->where('runs.countdown_time', $time);
        }

        $topScores = $query
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('highest_score', 'desc')
            ->orderBy('users.name', 'asc')
            ->limit(10)
            ->get();

        return response()->json(['topScores' => $topScores]);
    }
}
