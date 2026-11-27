<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    /**
     * Muestra todos los logros: desbloqueados con su fecha, pendientes y ocultos como "???"
     */
    public function index(Request $request): View
    {
        return view('main.achievements', [
            'achievements' => Achievement::orderBy('sort')->get(),
            'unlocked' => $request->user()->achievements()->get()->keyBy('id'),
        ]);
    }
}
