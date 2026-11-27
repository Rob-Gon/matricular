<?php

namespace Tests\Feature;

use App\Enums\CountdownModes;
use App\Enums\LetterSets;
use App\Models\Run;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_rankings_only_count_runs_with_at_least_one_attempted_word(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create(['name' => 'Jugadora']);
        Run::factory()->for($player)->attempts(valid: ['BARCO', 'CABRA', 'BRECA'])->create(['score' => 30]);
        Run::factory()->for($player)->attempts(invalid: ['BRCX'])->create(['score' => -5]);
        Run::factory()->for($player)->create(['score' => 999]);
        Run::factory()->create(['score' => 500]);

        $this->actingAs($viewer)->get(route('scores.view'))
            ->assertOk()
            ->assertViewHas('topScores', function ($topScores): bool {
                return $topScores->count() === 1
                    && $topScores[0]->name === 'Jugadora'
                    && (int) $topScores[0]->highest_score === 30
                    && (int) $topScores[0]->total_runs === 2;
            });
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
            ->viewData('topScores')
            ->pluck('name')
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
