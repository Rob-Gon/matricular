<?php

namespace Tests\Feature;

use App\Enums\LobbyFormats;
use App\Events\LobbyReacted;
use App\Events\LobbyUpdated;
use App\Models\Lobby;
use App\Models\User;
use App\Services\LobbyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LobbyReactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        Event::fake([LobbyUpdated::class, LobbyReacted::class]);
    }

    /**
     * @return array{0: Lobby, 1: User, 2: User}
     */
    private function lobby(): array
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $lobby = app(LobbyManager::class)->create($host, LobbyFormats::RACE, null);
        app(LobbyManager::class)->join($guest, $lobby->code, null);

        return [$lobby, $host, $guest];
    }

    public function test_a_player_reacts_and_everyone_gets_the_bubble_with_only_ids(): void
    {
        [$lobby, , $guest] = $this->lobby();
        $lobby->update(['last_activity_at' => now()->subMinutes(20)]);

        $this->actingAs($guest)->postJson(route('lobbies.react', $lobby), ['emoji' => '🔥'])->assertNoContent();

        Event::assertDispatched(LobbyReacted::class, fn (LobbyReacted $event) => $event->lobbyId === $lobby->id
            && $event->broadcastWith() === ['id' => $guest->id, 'emoji' => '🔥', 'horn' => null, 'shout' => null]
            && $event->broadcastAs() === 'lobby.reaction');
        $this->assertTrue($lobby->fresh()?->last_activity_at->equalTo(now()));
    }

    public function test_only_the_listed_reactions_are_accepted(): void
    {
        [$lobby, $host] = $this->lobby();

        $this->actingAs($host)->postJson(route('lobbies.react', $lobby), ['emoji' => '<b>hola</b>'])->assertUnprocessable()->assertJsonValidationErrors('emoji');
        $this->actingAs($host)->postJson(route('lobbies.react', $lobby), [])->assertUnprocessable();

        Event::assertNotDispatched(LobbyReacted::class);
    }

    public function test_outsiders_cannot_react_or_honk(): void
    {
        [$lobby] = $this->lobby();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson(route('lobbies.react', $lobby), ['emoji' => '👏'])->assertForbidden();
        $this->actingAs($outsider)->postJson(route('lobbies.horn', $lobby))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('lobbies.shout', $lobby))->assertForbidden();

        Event::assertNotDispatched(LobbyReacted::class);
    }

    public function test_a_player_honks_once_every_three_seconds(): void
    {
        [$lobby, $host, $guest] = $this->lobby();

        $this->actingAs($host)->postJson(route('lobbies.horn', $lobby))->assertNoContent();
        $this->actingAs($host)->postJson(route('lobbies.horn', $lobby))->assertTooManyRequests();
        // The cooldown is per player.
        $this->actingAs($guest)->postJson(route('lobbies.horn', $lobby))->assertNoContent();

        $this->travel(Lobby::SOUND_COOLDOWN_SECONDS)->seconds();
        $this->actingAs($host)->postJson(route('lobbies.horn', $lobby))->assertNoContent();

        Event::assertDispatchedTimes(LobbyReacted::class, 3);
        Event::assertDispatched(LobbyReacted::class, fn (LobbyReacted $event) => $event->emoji === null && $event->horn >= 0 && $event->horn <= 999);
    }

    public function test_the_shout_has_its_own_cooldown(): void
    {
        [$lobby, $host] = $this->lobby();

        $this->actingAs($host)->postJson(route('lobbies.horn', $lobby))->assertNoContent();
        // Honking does not use up the shout.
        $this->actingAs($host)->postJson(route('lobbies.shout', $lobby))->assertNoContent();
        $this->actingAs($host)->postJson(route('lobbies.shout', $lobby))->assertTooManyRequests();

        $this->travel(Lobby::SOUND_COOLDOWN_SECONDS)->seconds();
        $this->actingAs($host)->postJson(route('lobbies.shout', $lobby))->assertNoContent();

        Event::assertDispatched(LobbyReacted::class, fn (LobbyReacted $event) => $event->horn === null
            && $event->shout >= 0 && $event->shout <= 999
            && $event->broadcastWith()['shout'] === $event->shout);
        Event::assertDispatchedTimes(LobbyReacted::class, 3);
    }

    public function test_reactions_are_rate_limited(): void
    {
        [$lobby, $host] = $this->lobby();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($host)->postJson(route('lobbies.react', $lobby), ['emoji' => '😂'])->assertNoContent();
        }

        $this->actingAs($host)->postJson(route('lobbies.react', $lobby), ['emoji' => '😂'])->assertTooManyRequests();
    }

    public function test_the_grid_shows_the_reaction_buttons_and_the_horn_setting(): void
    {
        [$lobby, $host] = $this->lobby();

        $this->actingAs($host)->get(route('lobbies.show', $lobby))
            ->assertOk()->assertSee('aria-label="Calavera"', false)->assertSee('¡Pita!')->assertSee('¡Grita!')->assertSee('Bocinas y gritos de los demás');
    }
}
