<?php

namespace Tests\Feature;

use App\Enums\LobbyFormats;
use App\Events\LobbyUpdated;
use App\Events\RaceScored;
use App\Models\Lobby;
use App\Models\LobbyMatch;
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
        Event::fake([LobbyUpdated::class, RaceScored::class]);
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

        app(LobbyManager::class)->setRules($lobby, LobbyFormats::ROUNDS, LobbyFormats::ROUNDS->defaults());
        app(LobbyManager::class)->setReady($lobby, $guest, true);
        $this->actingAs($host)->postJson(route('lobbies.start', $lobby))->assertUnprocessable();

        $this->assertDatabaseCount('lobby_matches', 0);
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
        [$lobby] = $this->startedRace();
        $this->travel(130)->seconds();

        $this->artisan('lobbies:close-idle')->assertSuccessful();

        $this->assertNotNull(LobbyMatch::firstOrFail()->finished_at);
        $this->assertSame(0, LobbyMatch::firstOrFail()->runs()->whereNull('ended_at')->count());
        $this->assertSame(Lobby::STATUS_WAITING, $lobby->fresh()?->status);
    }
}
