<?php

namespace Tests\Feature;

use App\Enums\LobbyFormats;
use App\Events\FirstPlateUpdated;
use App\Events\LobbyUpdated;
use App\Events\RaceScored;
use App\Events\TiebreakUpdated;
use App\Models\Lobby;
use App\Models\LobbyMatch;
use App\Models\Run;
use App\Models\User;
use App\Services\LobbyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FirstWinsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        Event::fake([LobbyUpdated::class, RaceScored::class, TiebreakUpdated::class, FirstPlateUpdated::class]);
        Http::fake(['rae-api.com/*' => Http::response(['ok' => true])]);
    }

    /**
     * A started "first wins" match on plates BRC, CRB, TRN (or fewer), before the green light.
     *
     * @param  array<string, mixed>  $rules
     * @return array{0: Lobby, 1: User, 2: list<User>, 3: LobbyMatch}
     */
    private function startedMatch(array $rules = [], int $guests = 1): array
    {
        $manager = app(LobbyManager::class);
        $host = User::factory()->create();
        $lobby = $manager->create($host, LobbyFormats::FIRST, null);
        $manager->setRules($lobby, LobbyFormats::FIRST, [...LobbyFormats::FIRST->defaults(), 'plates' => 3, 'plate_seconds' => 30, 'tie_window_ms' => 150, ...$rules]);

        $players = [];
        foreach (User::factory()->count($guests)->create() as $guest) {
            $manager->join($guest, $lobby->code, null);
            $manager->setReady($lobby, $guest, true);
            $players[] = $guest;
        }

        $this->actingAs($host)->postJson(route('lobbies.start', $lobby))->assertOk();

        $match = LobbyMatch::firstOrFail();
        $plates = array_slice(['BRC', 'CRB', 'TRN'], 0, $match->plateCount());
        $this->assertCount($match->plateCount(), array_unique($match->plates));
        $match->update(['plates' => $plates]);
        $match->runs()->update(['plate' => 'BRC']);

        return [$lobby, $host, $players, $match];
    }

    private function word(User $user, Lobby $lobby, string $word, int $plate = 0): TestResponse
    {
        return $this->actingAs($user)->postJson(route('lobbies.race.word', $lobby), ['word' => $word, 'round' => $plate]);
    }

    private function sync(User $user, Lobby $lobby): TestResponse
    {
        return $this->actingAs($user)->postJson(route('lobbies.race.sync', $lobby));
    }

    private function toGreenLight(): void
    {
        $this->travel(LobbyMatch::LIGHTS_SECONDS + 1)->seconds();
    }

    public function test_the_plate_is_never_handed_out_early_and_there_is_no_pasamatricula(): void
    {
        [$lobby, $host] = $this->startedMatch();

        $this->actingAs($host)->get(route('lobbies.race', $lobby))->assertOk()->assertDontSee('BRC')->assertSee('Matrícula');
        $this->sync($host, $lobby)->assertStatus(409)->assertJsonMissingPath('chars');
        $this->word($host, $lobby, 'barco')->assertJsonPath('success', false)->assertJsonPath('chars', [])->assertJsonPath('score', 0);

        $this->toGreenLight();

        $this->sync($host, $lobby)->assertOk()->assertJsonPath('chars', ['B', 'R', 'C'])->assertJsonPath('first.locked', false);
        $this->actingAs($host)->postJson(route('lobbies.race.plate', $lobby))->assertUnprocessable()->assertJsonPath('score', 0);
    }

    public function test_the_first_valid_word_takes_the_plate_and_words_inside_the_tie_window_also_score(): void
    {
        [$lobby, $host, [$guest, $late], $match] = $this->startedMatch(guests: 2);
        $this->toGreenLight();

        $this->word($host, $lobby, 'barco')
            ->assertJsonPath('success', true)->assertJsonPath('score', 10)->assertJsonPath('first.locked', true);
        Event::assertDispatched(FirstPlateUpdated::class, fn (FirstPlateUpdated $event) => $event->winnerId === $host->id && $event->nextInMs === 150 + LobbyMatch::FIRST_PAUSE_MS);

        // 100 ms later: inside the 150 ms window, it scores too.
        $this->travel(100)->milliseconds();
        $this->word($guest, $lobby, 'cabra')->assertJsonPath('success', true)->assertJsonPath('score', 10);

        // 200 ms after the first: too late, but no penalty, and nothing recorded.
        $this->travel(100)->milliseconds();
        $this->word($late, $lobby, 'cobre')
            ->assertJsonPath('success', false)->assertJsonPath('score', 0)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, $host->name.' ya se ha llevado'));
        $this->assertSame(0, $match->runOf($late)?->runWords()->count());

        // The next plate only comes after the pause.
        $this->sync($guest, $lobby)->assertJsonPath('round', 0);
        $this->travel(LobbyMatch::FIRST_PAUSE_MS)->milliseconds();
        $this->sync($guest, $lobby)->assertJsonPath('round', 1)->assertJsonPath('chars', ['C', 'R', 'B'])->assertJsonPath('first.locked', false);
        Event::assertDispatched(FirstPlateUpdated::class, fn (FirstPlateUpdated $event) => $event->plateNumber === 1 && $event->nextInMs === null);

        // An answer to the old plate does not count on the new one.
        $this->word($late, $lobby, 'cobre', plate: 0)->assertJsonPath('success', false)->assertJsonPath('score', 0);
    }

    public function test_one_try_per_plate_and_the_plate_passes_once_everyone_failed(): void
    {
        [$lobby, $host, [$guest]] = $this->startedMatch();
        $this->toGreenLight();

        $this->word($host, $lobby, 'mesa')->assertJsonPath('success', false)->assertJsonPath('score', -5)->assertJsonPath('first.locked', true);
        $this->word($host, $lobby, 'barco')->assertJsonPath('success', false)->assertJsonPath('score', -5);
        Event::assertNotDispatched(FirstPlateUpdated::class);

        $this->word($guest, $lobby, 'mesa')->assertJsonPath('score', -5);
        Event::assertDispatched(FirstPlateUpdated::class, fn (FirstPlateUpdated $event) => $event->winnerId === null && $event->nextInMs === LobbyMatch::FIRST_PAUSE_MS);

        $this->travel(LobbyMatch::FIRST_PAUSE_MS)->milliseconds();
        // Passing the plate costs nothing.
        $this->sync($host, $lobby)->assertJsonPath('round', 1)->assertJsonPath('score', -5);
    }

    public function test_a_repeated_word_does_not_use_up_the_try(): void
    {
        [$lobby, $host] = $this->startedMatch();
        $this->toGreenLight();

        $this->word($host, $lobby, 'barco')->assertJsonPath('score', 10);
        $this->travel(150 + LobbyMatch::FIRST_PAUSE_MS)->milliseconds();
        $this->sync($host, $lobby)->assertJsonPath('round', 1);

        // BRC and CRB take the same letters: "barco" again scores nothing, but another word still can.
        $this->word($host, $lobby, 'barco', plate: 1)->assertJsonPath('success', false)->assertJsonPath('score', 10)->assertJsonPath('first.locked', false);
        $this->word($host, $lobby, 'cabra', plate: 1)->assertJsonPath('success', true)->assertJsonPath('score', 20);
    }

    public function test_the_next_plate_waits_for_a_word_that_arrived_in_time_and_is_still_being_judged(): void
    {
        [$lobby, $host, [$guest], $match] = $this->startedMatch();
        $this->toGreenLight();

        // The guest's word reached the server 50 ms after the host's and is still being judged.
        $judging = now()->addMilliseconds(50)->format('Y-m-d H:i:s.v');
        $this->word($host, $lobby, 'barco')->assertJsonPath('success', true);
        Run::withoutGlobalScope(Run::SOLO_SCOPE)->whereKey($match->runOf($guest)?->id)->update(['answering_since' => $judging]);

        $this->travel(150 + LobbyMatch::FIRST_PAUSE_MS)->milliseconds();
        $this->sync($host, $lobby)->assertJsonPath('round', 0);

        // A request that never finishes holds the plate only for a few seconds.
        $this->travel(LobbyMatch::ANSWER_STALE_SECONDS)->seconds();
        $this->sync($host, $lobby)->assertJsonPath('round', 1);
    }

    public function test_plates_pass_when_their_time_runs_out_and_the_last_one_ends_the_match(): void
    {
        [$lobby, $host, [$guest], $match] = $this->startedMatch(['plates' => 2, 'tie_resolution' => 'shared']);
        $this->toGreenLight();

        $this->travel(30_001)->milliseconds();
        $this->word($guest, $lobby, 'barco')->assertJsonPath('success', false)->assertJsonPath('score', 0)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'tiempo'));

        $this->travel(LobbyMatch::FIRST_PAUSE_MS)->milliseconds();
        $this->sync($guest, $lobby)->assertJsonPath('round', 1)->assertJsonPath('first.ends_in_ms', 30000);

        $this->word($host, $lobby, 'cabra', plate: 1)->assertJsonPath('success', true);
        $this->travel(150 + LobbyMatch::FIRST_PAUSE_MS)->milliseconds();
        $this->sync($guest, $lobby)->assertOk()->assertJsonPath('game_over', true);

        $match->refresh();
        $this->assertTrue($match->isFinished());
        $this->assertSame(0, $match->runs()->whereNull('ended_at')->count());
        $this->assertSame(Lobby::STATUS_WAITING, $lobby->fresh()?->status);
        $this->assertSame([$host->id, $guest->id], array_column($match->standings(), 'id'));
        Event::assertDispatched(FirstPlateUpdated::class, fn (FirstPlateUpdated $event) => $event->finished);

        $this->actingAs($guest)->get(route('lobbies.race', $lobby))->assertRedirect(route('lobbies.results', $lobby));
        $this->actingAs($guest)->get(route('lobbies.results', $lobby))->assertOk()->assertSee('2 matrículas');
    }
}
