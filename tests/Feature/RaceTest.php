<?php

namespace Tests\Feature;

use App\Enums\LobbyFormats;
use App\Events\LobbyUpdated;
use App\Events\RaceScored;
use App\Events\TiebreakUpdated;
use App\Models\Lobby;
use App\Models\LobbyMatch;
use App\Models\MatchTiebreak;
use App\Models\Run;
use App\Models\User;
use App\Services\LobbyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Whole seconds, so the race clock in these tests never straddles a second boundary.
        $this->freezeSecond();
        Event::fake([LobbyUpdated::class, RaceScored::class, TiebreakUpdated::class]);
        Http::fake(['rae-api.com/*' => Http::response(['ok' => true])]);
    }

    /**
     * A race lobby with a host and $guests ready players.
     *
     * @return array{0: Lobby, 1: User, 2: list<User>}
     */
    private function readyLobby(int $guests = 1): array
    {
        $manager = app(LobbyManager::class);
        $host = User::factory()->create();
        $lobby = $manager->create($host, LobbyFormats::RACE, null);

        $players = [];
        foreach (User::factory()->count($guests)->create() as $guest) {
            $manager->join($guest, $lobby->code, null);
            $manager->setReady($lobby, $guest, true);
            $players[] = $guest;
        }

        return [$lobby->fresh() ?? $lobby, $host, $players];
    }

    /** Starts the race with a known plate sequence and moves the clock past the traffic light. */
    private function startedRace(int $guests = 1): array
    {
        [$lobby, $host, $guests] = $this->readyLobby($guests);
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby))->assertOk()->assertJsonPath('status', 'playing');

        $match = LobbyMatch::firstOrFail();
        $match->update(['plates' => ['BRC', 'MSL', 'TRN']]);
        $match->runs()->update(['plate' => 'BRC']);
        $this->travel(LobbyMatch::LIGHTS_SECONDS + 2)->seconds();

        return [$lobby, $host, $guests, $match];
    }

    public function test_the_host_starts_once_everyone_is_ready_and_all_get_the_same_plates(): void
    {
        [$lobby, $host, [$guest]] = $this->readyLobby();

        $this->actingAs($guest)->postJson(route('lobbies.start', $lobby))->assertForbidden();
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby))->assertOk();

        $match = LobbyMatch::firstOrFail();
        $runs = $match->runs()->get();

        $this->assertSame(Lobby::STATUS_PLAYING, $lobby->fresh()?->status);
        $this->assertCount(LobbyMatch::SEQUENCE_LENGTH, $match->plates);
        $this->assertEqualsCanonicalizing([$host->id, $guest->id], $runs->pluck('user_id')->all());
        $this->assertSame([$match->plateAt(0)], $runs->pluck('plate')->unique()->values()->all());
        $this->assertTrue($runs->every(fn (Run $run) => $run->ends_at?->equalTo($match->ends_at) && $run->plate_dealt_at?->equalTo($match->starts_at)));
        $this->assertEquals(120, $match->starts_at->diffInSeconds($match->ends_at));

        // Only once.
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby))->assertUnprocessable();
    }

    public function test_a_race_needs_two_ready_players_and_the_race_format(): void
    {
        [$lobby, $host] = $this->readyLobby(guests: 0);
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby))->assertUnprocessable()->assertJsonValidationErrors('start');

        [$lobby, $host, [$guest]] = $this->readyLobby();
        app(LobbyManager::class)->setReady($lobby, $guest, false);
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby))->assertUnprocessable();

        // "First wins" is not playable yet.
        app(LobbyManager::class)->setRules($lobby, LobbyFormats::FIRST, LobbyFormats::FIRST->defaults());
        app(LobbyManager::class)->setReady($lobby, $guest, true);
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby))->assertUnprocessable();

        $this->assertDatabaseCount('lobby_matches', 0);
    }

    public function test_the_first_plate_is_only_handed_out_at_the_green_light(): void
    {
        [$lobby, $host] = $this->readyLobby();
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby));
        $match = LobbyMatch::firstOrFail();
        $match->update(['plates' => ['BRC', 'MSL', 'TRN']]);
        $match->runs()->update(['plate' => 'BRC']);

        $this->actingAs($host)->get(route('lobbies.race', $lobby))->assertOk()->assertDontSee('BRC');
        $this->actingAs($host)->postJson(route('lobbies.race.sync', $lobby))->assertStatus(409)->assertJsonMissingPath('chars');

        $this->travel(LobbyMatch::LIGHTS_SECONDS + 2)->seconds();
        $this->actingAs($host)->postJson(route('lobbies.race.sync', $lobby))->assertOk()->assertJsonPath('chars', ['B', 'R', 'C']);
        $this->actingAs($host)->get(route('lobbies.race', $lobby))->assertSee('BRC');
    }

    public function test_rounds_share_one_plate_and_a_word_scores_only_for_whoever_said_it_first(): void
    {
        [$lobby, $host, [$guest]] = $this->readyLobby();
        app(LobbyManager::class)->setRules($lobby, LobbyFormats::ROUNDS, [...LobbyFormats::ROUNDS->defaults(), 'rounds' => 3, 'round_seconds' => 20]);
        app(LobbyManager::class)->setReady($lobby, $guest, true);
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby))->assertOk();

        $match = LobbyMatch::firstOrFail();
        $this->assertCount(3, array_unique($match->plates));
        $this->assertEquals(60, $match->starts_at->diffInSeconds($match->ends_at));
        $match->update(['plates' => ['BRC', 'MSL', 'TRN']]);
        $match->runs()->update(['plate' => 'BRC']);
        $this->travel(LobbyMatch::LIGHTS_SECONDS + 2)->seconds();

        // Round 1: the plate stays after a valid word; the same word scores only once.
        $this->actingAs($host)->postJson(route('lobbies.race.word', $lobby), ['word' => 'barco', 'round' => 0])
            ->assertJsonPath('success', true)->assertJsonPath('score', 10)->assertJsonPath('chars', ['B', 'R', 'C']);
        $this->actingAs($guest)->postJson(route('lobbies.race.word', $lobby), ['word' => 'Barco', 'round' => 0])
            ->assertJsonPath('success', false)->assertJsonPath('score', 0)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'ya la dijo '.$host->name));
        $this->actingAs($guest)->postJson(route('lobbies.race.word', $lobby), ['word' => 'cabra', 'round' => 0])->assertJsonPath('score', 10);
        $this->actingAs($guest)->postJson(route('lobbies.race.plate', $lobby))->assertUnprocessable()->assertJsonPath('score', 10);

        // Round 2: a new plate for everyone; a word sent just before the change still answers round 1.
        $this->travel(20)->seconds();
        $this->actingAs($guest)->postJson(route('lobbies.race.word', $lobby), ['word' => 'barca', 'round' => 0])
            ->assertJsonPath('success', true)->assertJsonPath('round', 0);
        $this->actingAs($guest)->postJson(route('lobbies.race.sync', $lobby))->assertJsonPath('chars', ['M', 'S', 'L'])->assertJsonPath('round', 1);

        $this->travel(Run::LATENCY_GRACE_SECONDS + 1)->seconds();
        $this->actingAs($host)->postJson(route('lobbies.race.word', $lobby), ['word' => 'cobre', 'round' => 0])
            ->assertJsonPath('success', false)->assertJsonPath('score', 10);

        Event::assertDispatched(RaceScored::class, fn (RaceScored $event) => $event->userId === $guest->id && $event->score === 20);
        $this->assertSame(1, $match->runs()->where('user_id', $guest->id)->firstOrFail()->runWords()->where('reason', 'taken')->count());
    }

    public function test_words_wait_for_the_green_light_then_follow_the_shared_sequence(): void
    {
        [$lobby, $host, [$guest]] = $this->readyLobby();
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby));
        $match = LobbyMatch::firstOrFail();
        $match->update(['plates' => ['BRC', 'MSL', 'TRN']]);
        $match->runs()->update(['plate' => 'BRC']);

        $this->actingAs($guest)->postJson(route('lobbies.race.word', $lobby), ['word' => 'barco'])
            ->assertOk()->assertJsonPath('success', false)->assertJsonPath('score', 0);

        $this->travel(LobbyMatch::LIGHTS_SECONDS + 2)->seconds();

        $this->actingAs($guest)->postJson(route('lobbies.race.word', $lobby), ['word' => 'barco'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('score', 10)->assertJsonPath('chars', ['M', 'S', 'L']);
        $this->actingAs($host)->postJson(route('lobbies.race.plate', $lobby))
            ->assertOk()->assertJsonPath('score', -5)->assertJsonPath('chars', ['M', 'S', 'L']);

        Event::assertDispatched(RaceScored::class, fn (RaceScored $event) => $event->userId === $guest->id && $event->score === 10);
        Event::assertDispatched(RaceScored::class, fn (RaceScored $event) => $event->userId === $host->id && $event->score === -5);
        // No achievements in multiplayer.
        $this->assertDatabaseCount('user_achievements', 0);
    }

    public function test_race_runs_stay_out_of_solo_screens_rankings_and_stats(): void
    {
        [$lobby, $host] = $this->startedRace();
        $this->actingAs($host)->postJson(route('lobbies.race.word', $lobby), ['word' => 'barco'])->assertOk();
        $run = LobbyMatch::firstOrFail()->runOf($host);

        $this->assertNotNull($run);
        $this->assertSame(0, $host->runs()->count());
        $this->actingAs($host)->get(route('game.show', $run->id))->assertNotFound();
        $this->actingAs($host)->get(route('menu.view'))->assertViewHas('played', 0)->assertViewHas('best', 0);
        $this->actingAs($host)->get(route('scores.view'))->assertDontSee($host->name);
    }

    public function test_the_grid_sends_players_into_the_race_and_outsiders_get_nothing(): void
    {
        [$lobby, $host] = $this->startedRace();

        $this->actingAs($host)->get(route('lobbies.show', $lobby))->assertRedirect(route('lobbies.race', $lobby));
        $this->actingAs($host)->get(route('lobbies.race', $lobby))->assertOk()->assertSee('En directo')->assertSee('race(', false);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('lobbies.race', $lobby))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('lobbies.race.word', $lobby), ['word' => 'barco'])->assertForbidden();
    }

    public function test_the_race_finishes_when_the_clock_runs_out_and_the_lobby_returns_to_the_grid(): void
    {
        [$lobby, $host, [$guest]] = $this->startedRace();
        $this->actingAs($guest)->postJson(route('lobbies.race.word', $lobby), ['word' => 'barco']);

        // Ending early does nothing: the clock decides.
        $this->actingAs($host)->postJson(route('lobbies.race.end', $lobby))->assertOk();
        $this->assertNull(LobbyMatch::firstOrFail()->runOf($host)?->ended_at);
        $this->actingAs($host)->get(route('lobbies.results', $lobby))->assertOk()->assertSee('Llegando a meta');

        $this->travel(120)->seconds();
        $this->actingAs($host)->postJson(route('lobbies.race.end', $lobby))->assertOk();
        $this->assertNull(LobbyMatch::firstOrFail()->finished_at, 'the guest has not ended yet');

        $this->travel(Run::LATENCY_GRACE_SECONDS + 1)->seconds();
        $this->actingAs($host)->get(route('lobbies.results', $lobby))
            ->assertOk()
            ->assertSee('¡Meta!')
            ->assertSeeInOrder([$guest->name, $host->name])
            ->assertSee('Más palabras')
            ->assertSee('La más larga');

        $lobby->refresh();
        $this->assertNotNull(LobbyMatch::firstOrFail()->finished_at);
        $this->assertSame(Lobby::STATUS_WAITING, $lobby->status);
        $this->assertFalse((bool) $lobby->players()->where('user_id', $guest->id)->value('ready'));
        $this->actingAs($guest)->get(route('lobbies.race', $lobby))->assertRedirect(route('lobbies.results', $lobby));
        $this->actingAs($guest)->get(route('lobbies.show', $lobby))->assertOk();
    }

    public function test_equal_scores_share_the_position(): void
    {
        [, , , $match] = $this->startedRace(guests: 2);
        $runs = $match->runs()->orderBy('id')->get();
        $runs[0]->update(['score' => 20]);
        $runs[1]->update(['score' => 20]);
        $runs[2]->update(['score' => 5]);

        $this->assertSame([1, 1, 3], array_column($match->standings(), 'position'));
    }

    public function test_the_scheduler_closes_races_everyone_abandoned(): void
    {
        [$lobby, , , $match] = $this->startedRace();
        // Both players end on 0: a tiebreak for first place starts, and nobody plays it either.
        $this->travel(130)->seconds();
        $this->artisan('lobbies:close-idle')->assertSuccessful();

        $this->assertNotNull($match->fresh()?->finished_at);
        $this->assertSame(0, $match->runs()->whereNull('ended_at')->count());
        $this->assertNotNull($match->activeTiebreak());
        $this->assertSame(Lobby::STATUS_PLAYING, $lobby->fresh()?->status);

        $this->travel(MatchTiebreak::COUNTDOWN_SECONDS + MatchTiebreak::SECONDS + Run::LATENCY_GRACE_SECONDS + 1)->seconds();
        $this->artisan('lobbies:close-idle')->assertSuccessful();

        $this->assertNull($match->activeTiebreak());
        $this->assertSame(Lobby::STATUS_WAITING, $lobby->fresh()?->status);
    }

    /**
     * Ends a race with the given final scores (in run order) and returns the match.
     *
     * @param  list<int>  $scores
     */
    private function finishedRace(array $scores, string $tieResolution = 'tiebreak'): array
    {
        [$lobby, $host, $guests, $match] = $this->startedRace(guests: count($scores) - 1);
        $match->update(['settings' => [...$match->settings, 'tie_resolution' => $tieResolution]]);
        foreach ($match->runs()->orderBy('id')->get() as $i => $run) {
            $run->update(['score' => $scores[$i]]);
        }

        $this->travel(130)->seconds();
        $this->actingAs($host)->get(route('lobbies.results', $lobby))->assertOk();

        return [$lobby, $host, $guests, $match->fresh()];
    }

    public function test_tied_podium_players_play_a_tiebreak_and_the_first_valid_word_wins(): void
    {
        [$lobby, $host, [$first, $second], $match] = $this->finishedRace([5, 20, 20]);
        $tiebreak = $match->activeTiebreak();

        $this->assertNotNull($tiebreak);
        $this->assertSame(1, $tiebreak->position);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $tiebreak->player_ids);
        $this->assertSame(Lobby::STATUS_PLAYING, $lobby->fresh()?->status);
        Event::assertDispatched(TiebreakUpdated::class);
        $tiebreak->update(['plate' => 'BRC']);

        $this->actingAs($host)->get(route('lobbies.results', $lobby))->assertSee('juegan una matrícula de desempate')->assertDontSee('BRC');
        $this->actingAs($host)->postJson(route('lobbies.tiebreak', $lobby), ['word' => 'barco'])->assertForbidden();
        $this->actingAs($first)->postJson(route('lobbies.tiebreak', $lobby), ['word' => 'barco'])->assertJsonPath('success', false);

        $this->travel(MatchTiebreak::COUNTDOWN_SECONDS + 1)->seconds();
        $this->actingAs($second)->postJson(route('lobbies.tiebreak', $lobby), ['word' => 'mesa'])
            ->assertOk()->assertJsonPath('success', false);
        $this->actingAs($first)->postJson(route('lobbies.tiebreak', $lobby), ['word' => 'barco'])
            ->assertOk()->assertJsonPath('success', true);
        $this->actingAs($second)->postJson(route('lobbies.tiebreak', $lobby), ['word' => 'cabra'])->assertStatus(409);

        $this->assertSame([$first->id => 1, $second->id => 2, $host->id => 3], array_column($match->standings(), 'position', 'id'));
        $this->assertSame(Lobby::STATUS_WAITING, $lobby->fresh()?->status);
        $this->actingAs($host)->get(route('lobbies.results', $lobby))->assertSee('ganó '.$first->name);
        // Race scores never change.
        $this->assertSame([5, 20, 20], $match->runs()->orderBy('id')->pluck('score')->all());
    }

    public function test_a_tiebreak_nobody_solves_leaves_the_position_shared(): void
    {
        [$lobby, $host, , $match] = $this->finishedRace([10, 10]);

        $this->travel(MatchTiebreak::COUNTDOWN_SECONDS + MatchTiebreak::SECONDS + Run::LATENCY_GRACE_SECONDS + 1)->seconds();
        $this->actingAs($host)->get(route('lobbies.results', $lobby))->assertOk()->assertSee('comparten puesto');

        $this->assertSame(1, $match->tiebreaks()->count());
        $this->assertSame([1, 1], array_column($match->standings(), 'position'));
        $this->assertSame(Lobby::STATUS_WAITING, $lobby->fresh()?->status);
    }

    public function test_only_podium_ties_are_played_and_only_with_the_tiebreak_option(): void
    {
        [$lobby, , , $match] = $this->finishedRace([50, 40, 30, 10, 10]);
        $this->assertSame(0, $match->tiebreaks()->count());
        $this->assertSame(Lobby::STATUS_WAITING, $lobby->fresh()?->status);

        LobbyMatch::query()->delete();
        [$lobby, , , $match] = $this->finishedRace([10, 10], tieResolution: 'shared');
        $this->assertSame(0, $match->tiebreaks()->count());
        $this->assertSame(Lobby::STATUS_WAITING, $lobby->fresh()?->status);
    }
}
