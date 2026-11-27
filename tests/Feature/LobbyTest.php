<?php

namespace Tests\Feature;

use App\Enums\LobbyFormats;
use App\Events\LobbyUpdated;
use App\Models\Lobby;
use App\Models\LobbyPlayer;
use App\Models\User;
use App\Services\LobbyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LobbyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([LobbyUpdated::class]);
    }

    private function lobby(?User $host = null, LobbyFormats $format = LobbyFormats::RACE, ?string $password = null): Lobby
    {
        return app(LobbyManager::class)->create($host ?? User::factory()->create(), $format, $password);
    }

    private function joinAs(User $user, Lobby $lobby, ?string $password = null): void
    {
        app(LobbyManager::class)->join($user, $lobby->code, $password);
    }

    public function test_creating_a_lobby_seats_the_host_and_hashes_the_password(): void
    {
        $host = User::factory()->create();

        $this->actingAs($host)->post(route('lobbies.store'), ['format' => 'first', 'password' => 'secreta'])
            ->assertRedirect();

        $lobby = Lobby::sole();
        $this->assertMatchesRegularExpression('/^[0-9]{4}[BCDFGHJLMNPRST]{3}$/', $lobby->code);
        $this->assertSame($host->id, $lobby->host_id);
        $this->assertSame(LobbyFormats::FIRST, $lobby->format);
        $this->assertSame(15, $lobby->settings['plates']);
        $this->assertTrue(Hash::check('secreta', $lobby->password));
        $this->assertTrue($lobby->players()->sole()->ready);
    }

    public function test_players_join_by_code_written_like_a_plate(): void
    {
        $lobby = $this->lobby(password: 'secreta');
        $guest = User::factory()->create();
        $spaced = substr($lobby->code, 0, 4).' '.strtolower(substr($lobby->code, 4));

        $this->actingAs($guest)->post(route('lobbies.join'), ['code' => $spaced, 'join_password' => 'mala'])
            ->assertSessionHasErrors(['join_password' => 'La contraseña de la sala no es correcta.']);
        $this->actingAs($guest)->post(route('lobbies.join'), ['code' => '0000ZZZ'])
            ->assertSessionHasErrors(['code' => 'No hay ninguna sala con ese código.']);

        $this->actingAs($guest)->post(route('lobbies.join'), ['code' => $spaced, 'join_password' => 'secreta'])
            ->assertRedirect(route('lobbies.show', $lobby));

        $this->assertSame(2, $lobby->players()->count());
        $this->assertFalse($lobby->players()->where('user_id', $guest->id)->value('ready'));
        Event::assertDispatched(LobbyUpdated::class, fn (LobbyUpdated $event) => count($event->state['players']) === 2);
    }

    public function test_full_or_started_lobbies_refuse_new_players(): void
    {
        $lobby = $this->lobby();
        User::factory()->count(15)->create()->each(fn (User $user) => $this->joinAs($user, $lobby));

        $this->actingAs(User::factory()->create())->post(route('lobbies.join'), ['code' => $lobby->code])
            ->assertSessionHasErrors(['code' => 'La sala está llena: ya hay 16 jugadores.']);

        $started = $this->lobby();
        $started->update(['status' => Lobby::STATUS_PLAYING]);
        $this->actingAs(User::factory()->create())->post(route('lobbies.join'), ['code' => $started->code])
            ->assertSessionHasErrors(['code' => 'La partida de esa sala ya ha empezado.']);
    }

    public function test_a_player_is_in_one_lobby_at_a_time(): void
    {
        $player = User::factory()->create();
        $first = $this->lobby($player);
        $second = $this->lobby();

        $this->joinAs($player, $second);

        $this->assertModelMissing($first);
        $this->assertSame($second->id, LobbyPlayer::where('user_id', $player->id)->value('lobby_id'));
    }

    public function test_only_players_see_the_lobby(): void
    {
        $lobby = $this->lobby();

        $this->actingAs($lobby->host)->get(route('lobbies.show', $lobby))->assertOk()->assertSee('Parrilla de salida');
        $this->actingAs(User::factory()->create())->get(route('lobbies.show', $lobby))
            ->assertRedirect(route('lobbies.index', ['codigo' => $lobby->code]));
    }

    public function test_the_presence_channel_admits_only_players_and_hides_emails(): void
    {
        $lobby = $this->lobby();
        $channel = Broadcast::driver()->getChannels()->get('lobby.{lobbyId}');

        $this->assertSame(['id' => $lobby->host_id, 'name' => $lobby->host->name], $channel($lobby->host, $lobby->id));
        $this->assertFalse($channel(User::factory()->create(), $lobby->id));
    }

    public function test_when_the_host_leaves_the_oldest_player_takes_over(): void
    {
        $lobby = $this->lobby();
        [$second, $third] = User::factory()->count(2)->create();
        $this->joinAs($second, $lobby);
        $this->travel(1)->seconds();
        $this->joinAs($third, $lobby);

        $this->actingAs($lobby->host)->post(route('lobbies.leave', $lobby))->assertRedirect(route('lobbies.index'));
        $this->assertSame($second->id, $lobby->fresh()->host_id);

        $this->actingAs($second)->post(route('lobbies.leave', $lobby));
        $this->actingAs($third)->post(route('lobbies.leave', $lobby));
        $this->assertModelMissing($lobby);
    }

    public function test_only_the_host_kicks_and_never_themselves(): void
    {
        $lobby = $this->lobby();
        [$guest, $other] = User::factory()->count(2)->create();
        $this->joinAs($guest, $lobby);
        $this->joinAs($other, $lobby);

        $this->actingAs($guest)->postJson(route('lobbies.kick', $lobby), ['player' => $other->id])->assertForbidden();
        $this->actingAs($lobby->host)->postJson(route('lobbies.kick', $lobby), ['player' => $lobby->host_id])->assertUnprocessable();

        $this->actingAs($lobby->host)->postJson(route('lobbies.kick', $lobby), ['player' => $other->id])
            ->assertOk()
            ->assertJsonCount(2, 'players');
        $this->actingAs($other)->get(route('lobbies.show', $lobby))->assertRedirect();
    }

    public function test_players_set_their_colour_and_ready_state(): void
    {
        $lobby = $this->lobby();
        $guest = User::factory()->create();
        $this->joinAs($guest, $lobby);

        $this->actingAs($guest)->postJson(route('lobbies.color', $lobby), ['color' => '#000000'])->assertUnprocessable();
        $this->actingAs($guest)->postJson(route('lobbies.color', $lobby), ['color' => '#9C7BB8'])->assertOk();
        $this->actingAs($guest)->postJson(route('lobbies.ready', $lobby), ['ready' => true])
            ->assertOk()
            ->assertJsonPath('players.1.ready', true)
            ->assertJsonPath('players.1.color', '#9C7BB8');

        $this->actingAs(User::factory()->create())->postJson(route('lobbies.ready', $lobby), ['ready' => true])->assertForbidden();
    }

    public function test_the_host_sets_the_rules_within_the_agreed_limits(): void
    {
        $lobby = $this->lobby();
        $guest = User::factory()->create();
        $this->joinAs($guest, $lobby);
        app(LobbyManager::class)->setReady($lobby, $guest, true);
        $base = ['letter_set' => 'full', 'ordered' => '1', 'tie_resolution' => 'shared'];

        $this->actingAs($guest)->putJson(route('lobbies.rules', $lobby), [...$base, 'format' => 'rounds', 'rounds' => 5, 'round_seconds' => 20])
            ->assertForbidden();
        $this->actingAs($lobby->host)->putJson(route('lobbies.rules', $lobby), [...$base, 'format' => 'rounds', 'rounds' => 31, 'round_seconds' => 301])
            ->assertJsonValidationErrors(['rounds', 'round_seconds']);
        $this->actingAs($lobby->host)->putJson(route('lobbies.rules', $lobby), [...$base, 'format' => 'first', 'plates' => 51, 'plate_seconds' => 5, 'tie_window_ms' => 100])
            ->assertJsonValidationErrors(['plates', 'plate_seconds', 'tie_window_ms']);
        $this->actingAs($lobby->host)->putJson(route('lobbies.rules', $lobby), [...$base, 'format' => 'race', 'countdown_time' => 'Personalizado', 'custom_minutes' => 61])
            ->assertJsonValidationErrors(['custom_minutes']);

        $this->actingAs($lobby->host)->putJson(route('lobbies.rules', $lobby), [...$base, 'format' => 'race', 'countdown_time' => 'Personalizado', 'custom_minutes' => 45])
            ->assertOk()
            ->assertJsonPath('settings.countdown_seconds', 2700)
            ->assertJsonPath('settings.letter_set', 'full')
            ->assertJsonPath('rules.0.value', '45 minutos (personalizado)');

        // New rules: every guest confirms "ready" again.
        $this->assertFalse($lobby->players()->where('user_id', $guest->id)->value('ready'));
    }

    public function test_idle_lobbies_close_but_running_matches_do_not(): void
    {
        $idle = $this->lobby();
        $playing = $this->lobby();
        $active = $this->lobby();
        Lobby::whereKey([$idle->id, $playing->id])->update(['last_activity_at' => now()->subMinutes(31)]);
        $playing->update(['status' => Lobby::STATUS_PLAYING, 'last_activity_at' => now()->subMinutes(31)]);

        $this->artisan('lobbies:close-idle')->assertSuccessful();

        $this->assertModelMissing($idle);
        $this->assertModelExists($playing);
        $this->assertModelExists($active);
        Event::assertDispatched(LobbyUpdated::class, fn (LobbyUpdated $event) => $event->state['closed'] === true);
    }

    public function test_the_hub_explains_why_a_player_is_back(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('lobbies.index', ['expulsado' => 1]))->assertSee('El anfitrión te ha sacado de la sala.');
        $this->actingAs($user)->get(route('lobbies.index', ['cerrada' => 1]))->assertSee('La sala se ha cerrado por inactividad.');

        $lobby = $this->lobby($user);
        $this->actingAs($user)->get(route('lobbies.index'))->assertSee('Volver a la sala')->assertSee(route('lobbies.show', $lobby));
    }
}
