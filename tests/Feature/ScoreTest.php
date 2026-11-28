<?php

namespace Tests\Feature;

use App\Enums\CountdownModes;
use App\Enums\LetterSets;
use App\Enums\LobbyFormats;
use App\Models\LobbyMatch;
use App\Models\Run;
use App\Models\User;
use App\Services\LobbyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_top_lists_the_best_games_with_at_least_one_attempted_word(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create(['name' => 'Jugadora']);
        Run::factory()->for($player)->attempts(valid: ['BARCO', 'CABRA', 'BRECA'])->create(['score' => 30]);
        Run::factory()->for($player)->attempts(valid: ['BARCO', 'CABRA'])->create(['score' => 20]);
        Run::factory()->for($player)->attempts(invalid: ['BRCX'])->create(['score' => -5]);
        // No word attempted: never ranks, whatever the score.
        Run::factory()->for($player)->create(['score' => 999]);
        Run::factory()->create(['score' => 500]);
        Run::factory()->count(10)->attempts(valid: ['BARCO'])->create(['score' => 10]);

        $top = $this->actingAs($viewer)->get(route('scores.view'))->assertOk()->viewData('topRuns');

        // Games, not players: the same player may appear more than once.
        $this->assertCount(10, $top);
        $this->assertSame(['Jugadora', 'Jugadora'], $top->take(2)->pluck('user.name')->all());
        $this->assertSame([30, 20, 10], $top->take(3)->pluck('score')->all());
    }

    public function test_today_follows_the_spanish_calendar_day(): void
    {
        // 23:30 in Madrid (UTC+1 in winter): the Spanish day started at 23:00 UTC the day before.
        $this->travelTo(Carbon::parse('2026-11-28 22:30:00', 'UTC'));
        $yesterday = Run::factory()->attempts(valid: ['BARCO'])->create(['score' => 90, 'created_at' => Carbon::parse('2026-11-27 22:59:00', 'UTC')]);
        $today = Run::factory()->attempts(valid: ['BARCO'])->create(['score' => 20, 'created_at' => Carbon::parse('2026-11-27 23:01:00', 'UTC')]);

        $view = $this->actingAs($today->user)->get(route('scores.view'))->assertOk();

        $this->assertSame([$yesterday->id, $today->id], $view->viewData('topRuns')->pluck('id')->all());
        $this->assertSame([$today->id], $view->viewData('todayRuns')->pluck('id')->all());
    }

    public function test_your_best_games_are_only_yours(): void
    {
        $mine = Run::factory()->attempts(valid: ['BARCO'])->create(['score' => 20]);
        Run::factory()->attempts(valid: ['BARCO'])->create(['score' => 90]);
        $worse = Run::factory()->for($mine->user)->attempts(valid: ['BARCO'])->create(['score' => 10]);

        $this->actingAs($mine->user)->get(route('scores.view'))
            ->assertOk()
            ->assertViewHas('bestRuns', fn ($runs) => $runs->pluck('id')->all() === [$mine->id, $worse->id])
            ->assertSee('Tus mejores partidas');
    }

    public function test_multiplayer_games_rank_on_their_own_tab_once_the_match_is_over(): void
    {
        $solo = Run::factory()->attempts(valid: ['BARCO'])->create(['score' => 500]);
        $race = $this->multiplayerRun(LobbyFormats::RACE, 40);
        $first = $this->multiplayerRun(LobbyFormats::FIRST, 30);
        $running = $this->multiplayerRun(LobbyFormats::RACE, 900, finished: false);

        $ids = fn (array $query) => $this->actingAs($solo->user)
            ->get(route('scores.view', $query))
            ->assertOk()
            ->viewData('topRuns')
            ->pluck('id')
            ->all();

        $this->assertSame([$solo->id], $ids([]));
        $this->assertSame([$race->id, $first->id], $ids(['tab' => 'multi']));
        $this->assertSame([$first->id], $ids(['tab' => 'multi', 'format' => 'first']));
        $this->assertSame([], $ids(['tab' => 'multi', 'ordered' => '1']));
        // The solo "mode" filter does not apply to multiplayer, and a bad format is ignored.
        $this->assertSame([$race->id, $first->id], $ids(['tab' => 'multi', 'mode' => 'Infinito', 'format' => 'nope']));

        // A match still being played shows in your latest games, never in the rankings.
        $this->actingAs($running->user)->get(route('scores.view', ['tab' => 'multi']))
            ->assertOk()
            ->assertSee('Carrera, Letras simples, Cualquier orden')
            ->assertViewHas('recentRuns', fn ($runs) => $runs->pluck('id')->all() === [$running->id])
            ->assertViewHas('bestRuns', fn ($runs) => $runs->isEmpty())
            ->assertViewHas('filters', [])
            ->assertDontSee('Quitar filtros');
        $this->actingAs($first->user)->get(route('scores.view', ['tab' => 'multi']))
            ->assertSee('El primero gana, Letras simples, Cualquier orden');
    }

    private function multiplayerRun(LobbyFormats $format, int $score, bool $finished = true): Run
    {
        $host = User::factory()->create();
        $lobby = app(LobbyManager::class)->create($host, $format, null);
        $match = LobbyMatch::create([
            'lobby_id' => $lobby->id,
            'format' => $format,
            'settings' => $lobby->settings,
            'plates' => ['BRC'],
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->subMinutes(3),
            'finished_at' => $finished ? now()->subMinutes(3) : null,
        ]);

        return Run::factory()->for($host)->attempts(valid: ['BARCO'])->create(['score' => $score, 'lobby_match_id' => $match->id, 'mode' => 'Contrareloj']);
    }

    public function test_rankings_filter_by_settings_and_time(): void
    {
        $simple = Run::factory()->attempts(valid: ['BARCO'])->create(['score' => 40]);
        $hard = Run::factory()->countdown(CountdownModes::TREINTA_SEGUNDOS)
            ->settings(LetterSets::FULL, ordered: true)
            ->attempts(valid: ['BARCO'])
            ->create(['score' => 20]);

        $names = fn (array $query) => $this->actingAs($simple->user)
            ->get(route('scores.view', $query))
            ->assertOk()
            ->viewData('topRuns')
            ->pluck('user.name')
            ->all();

        $this->assertSame([$hard->user->name], $names(['letter_set' => 'full', 'ordered' => '1']));
        $this->assertSame([$simple->user->name], $names(['ordered' => '0']));
        $this->assertSame([$hard->user->name], $names(['mode' => 'Contrareloj', 'time' => '30 segundos']));
        $this->assertSame([], $names(['mode' => 'Contrareloj', 'time' => '5 minutos']));

        // Invalid values are ignored instead of failing the page; a time without countdown mode is dropped.
        $this->assertCount(2, $names(['letter_set' => 'nope', 'ordered' => 'x', 'time' => '5 minutos']));
    }

    public function test_recent_runs_link_to_their_trip_receipt(): void
    {
        $run = Run::factory()->attempts(valid: ['BARCO'])->create(['score' => 15, 'ended_at' => now()]);

        $this->actingAs($run->user)->get(route('scores.view'))
            ->assertOk()
            ->assertSee(route('game.summary', $run))
            ->assertSee('Sin límite, Letras simples, Cualquier orden');
    }

    public function test_the_menu_shows_the_personal_best_and_links_to_both_modes(): void
    {
        $run = Run::factory()->attempts(valid: ['BARCO'])->create(['score' => 120]);

        $this->actingAs($run->user)->get(route('menu.view'))
            ->assertOk()
            ->assertSee('120 puntos, tu mejor marca')
            ->assertSee('1 partida jugada')
            ->assertSee(route('game.setup', ['mode' => 'Contrareloj']), false)
            ->assertSee(route('game.setup', ['mode' => 'Infinito']), false);
    }

    public function test_dates_are_sent_in_utc_for_the_browser_to_localize(): void
    {
        $this->travelTo(Carbon::parse('2026-11-27 12:24:00', 'UTC'));
        $run = Run::factory()->attempts(valid: ['BARCO'])->create();

        $this->actingAs($run->user)->get(route('scores.view'))
            ->assertSee('datetime="2026-11-27T12:24:00Z"', false)
            ->assertSee('27/11/2026, 12:24 UTC');
    }
}
