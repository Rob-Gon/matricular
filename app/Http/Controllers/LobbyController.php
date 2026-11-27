<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lobbies\CreateLobbyRequest;
use App\Http\Requests\Lobbies\JoinLobbyRequest;
use App\Http\Requests\Lobbies\UpdateLobbyRulesRequest;
use App\Models\Lobby;
use App\Models\LobbyPlayer;
use App\Services\LobbyManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LobbyController extends Controller
{
    public function __construct(private LobbyManager $lobbies) {}

    /** "Jugar con amigos": create a room or join one with its code. */
    public function index(Request $request): View|RedirectResponse
    {
        $current = LobbyPlayer::where('user_id', $request->user()->id)->with('lobby')->first();

        // The lobby screen sends players here when they were kicked or the room closed.
        $notice = match (true) {
            $request->boolean('expulsado') => 'El anfitrión te ha sacado de la sala.',
            $request->boolean('cerrada') => 'La sala se ha cerrado por inactividad.',
            default => null,
        };

        return view('lobbies.index', [
            'current' => $current?->lobby,
            'code' => (string) $request->query('codigo', ''),
            'notice' => $notice,
        ]);
    }

    public function store(CreateLobbyRequest $request): RedirectResponse
    {
        $lobby = $this->lobbies->create($request->user(), $request->lobbyFormat(), $request->validated('password'));

        return redirect()->route('lobbies.show', $lobby);
    }

    public function join(JoinLobbyRequest $request): RedirectResponse
    {
        $lobby = $this->lobbies->join($request->user(), $request->validated('code'), $request->validated('join_password'));

        return redirect()->route('lobbies.show', $lobby);
    }

    public function show(Request $request, Lobby $lobby): View|RedirectResponse
    {
        if (! $request->user()->can('view', $lobby)) {
            return redirect()->route('lobbies.index', ['codigo' => $lobby->code])
                ->with('error', 'No estás en esa sala. Escribe su código para entrar.');
        }

        return view('lobbies.show', [
            'lobby' => $lobby,
            'state' => $lobby->state(),
            'colors' => Lobby::CAR_COLORS,
        ]);
    }

    public function leave(Request $request, Lobby $lobby): RedirectResponse
    {
        Gate::authorize('view', $lobby);
        $this->lobbies->leave($lobby, $request->user());

        return redirect()->route('lobbies.index')->with('success', 'Has salido de la sala.');
    }

    public function ready(Request $request, Lobby $lobby): JsonResponse
    {
        Gate::authorize('view', $lobby);
        $ready = $request->validate(['ready' => ['required', 'boolean']]);

        return response()->json($this->lobbies->setReady($lobby, $request->user(), (bool) $ready['ready'])->state());
    }

    public function color(Request $request, Lobby $lobby): JsonResponse
    {
        Gate::authorize('view', $lobby);
        $color = $request->validate(['color' => ['required', Rule::in(array_keys(Lobby::CAR_COLORS))]]);

        return response()->json($this->lobbies->setColor($lobby, $request->user(), $color['color'])->state());
    }

    public function rules(UpdateLobbyRulesRequest $request, Lobby $lobby): JsonResponse
    {
        Gate::authorize('manage', $lobby);

        return response()->json($this->lobbies->setRules($lobby, $request->lobbyFormat(), $request->settings())->state());
    }

    public function kick(Request $request, Lobby $lobby): JsonResponse
    {
        Gate::authorize('manage', $lobby);
        $player = $request->validate(['player' => ['required', 'integer']]);
        $this->lobbies->kick($lobby, (int) $player['player']);

        return response()->json(($lobby->fresh() ?? $lobby)->state());
    }
}
