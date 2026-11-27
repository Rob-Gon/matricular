<?php

namespace Tests\Feature;

use App\Enums\CountdownModes;
use App\Enums\GameDifficulties;
use App\Enums\GameModes;
use App\Models\Run;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDictionary(bool $exists): void
    {
        Http::fake(['rae-api.com/*' => Http::response(['ok' => $exists], $exists ? 200 : 404)]);
    }

    public function test_start_creates_a_countdown_run_with_server_side_plate_and_deadline(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('game.start'), [
            'mode' => GameModes::CONTRARELOJ->value,
            'difficulty' => GameDifficulties::FACIL->value,
            'countdown_time' => CountdownModes::UN_MINUTO->value,
        ]);

        $run = Run::sole();
        $response->assertRedirect(route('game.show', $run));
        $this->assertTrue($run->user->is($user));
        $this->assertMatchesRegularExpression('/^[BCDFGHJLMNPRST]{3}$/', $run->plate);
        $this->assertEqualsWithDelta(now()->addMinute()->timestamp, $run->ends_at->timestamp, 2);
    }

    public function test_infinite_run_has_no_deadline_even_if_a_time_is_sent(): void
    {
        $this->actingAs(User::factory()->create())->post(route('game.start'), [
            'mode' => GameModes::INFINITO->value,
            'difficulty' => GameDifficulties::MEDIO->value,
            'countdown_time' => CountdownModes::UN_MINUTO->value,
        ]);

        $run = Run::sole();
        $this->assertNull($run->ends_at);
        $this->assertNull($run->countdown_time);
    }

    public function test_start_rejects_invalid_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('game.start'), ['mode' => GameModes::CONTRARELOJ->value, 'difficulty' => 'Imposible'])
            ->assertRedirect(route('menu.view'));

        $this->assertDatabaseCount('runs', 0);
    }

    public function test_a_user_cannot_play_another_users_run(): void
    {
        $run = Run::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('game.show', $run))->assertForbidden();
        $this->actingAs($intruder)->postJson(route('game.check-word', $run), ['word' => 'barco'])->assertForbidden();
        $this->actingAs($intruder)->postJson(route('game.change-plate', $run))->assertForbidden();
        $this->actingAs($intruder)->postJson(route('game.end', $run))->assertForbidden();
    }

    public function test_a_valid_word_scores_and_changes_the_plate(): void
    {
        $this->fakeDictionary(true);
        $run = Run::factory()->create(['plate' => 'BRC']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'barco'])
            ->assertOk()
            ->assertJson(['success' => true, 'score' => Run::POINTS_CORRECT]);

        $run->refresh();
        $this->assertSame(['BARCO'], $run->words_correct);
        $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $run->plate);
    }

    public function test_the_plate_comes_from_the_server_not_from_the_request(): void
    {
        Http::fake();
        $run = Run::factory()->create(['plate' => 'XYZ']);

        // The old client sent char1..3; a forged plate must be ignored.
        $this->actingAs($run->user)->postJson(route('game.check-word', $run), [
            'word' => 'barco', 'char1' => 'B', 'char2' => 'R', 'char3' => 'C',
        ])->assertJson(['success' => false, 'score' => -Run::POINTS_PENALTY]);

        Http::assertNothingSent();
    }

    public function test_repeated_plate_letters_must_all_appear(): void
    {
        Http::fake();
        $run = Run::factory()->create(['plate' => 'SSL']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'sal'])
            ->assertJson(['success' => false]);

        Http::assertNothingSent();
    }

    public function test_hard_difficulty_requires_the_letters_in_plate_order(): void
    {
        $this->fakeDictionary(true);
        $run = Run::factory()->difficulty(GameDifficulties::DIFICIL)->create(['plate' => 'CRB']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'barco'])
            ->assertJson(['success' => false, 'score' => -Run::POINTS_PENALTY]);

        $run->update(['plate' => 'BRC']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'barco'])
            ->assertJson(['success' => true]);
    }

    public function test_a_repeated_word_does_not_score_again(): void
    {
        $this->fakeDictionary(true);
        $run = Run::factory()->create(['plate' => 'BRC', 'words_correct' => ['BARCO'], 'score' => 10]);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'Barco'])
            ->assertJson(['success' => false, 'score' => 10]);

        $this->assertSame('BRC', $run->fresh()->plate);
    }

    public function test_a_word_missing_from_the_dictionary_is_penalized(): void
    {
        $this->fakeDictionary(false);
        $run = Run::factory()->create(['plate' => 'BRC']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'brcaaa'])
            ->assertJson(['success' => false, 'score' => -Run::POINTS_PENALTY]);

        $this->assertSame(['BRCAAA'], $run->fresh()->words_error);
    }

    public function test_a_dictionary_outage_does_not_penalize(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $run = Run::factory()->create(['plate' => 'BRC']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'barco'])
            ->assertOk()
            ->assertJson(['success' => false, 'score' => 0]);

        $this->assertSame([], $run->fresh()->words_error);
    }

    public function test_words_after_the_countdown_are_rejected(): void
    {
        Http::fake();
        $run = Run::factory()->countdown()->create([
            'plate' => 'BRC',
            'ends_at' => now()->subSeconds(Run::LATENCY_GRACE_SECONDS + 1),
        ]);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'barco'])
            ->assertStatus(409)
            ->assertJson(['game_over' => true, 'score' => 0]);

        Http::assertNothingSent();
    }

    public function test_words_within_the_latency_grace_are_accepted(): void
    {
        $this->fakeDictionary(true);
        $run = Run::factory()->countdown()->create(['plate' => 'BRC', 'ends_at' => now()->subSecond()]);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'barco'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_changing_the_plate_costs_points(): void
    {
        $run = Run::factory()->create(['plate' => 'BRC']);

        $this->actingAs($run->user)->postJson(route('game.change-plate', $run))
            ->assertOk()
            ->assertJson(['score' => -Run::POINTS_PENALTY]);

        $this->assertSame([['B', 'R', 'C']], $run->fresh()->chars_skipped);
    }

    public function test_locked_updates_start_from_the_latest_row_instead_of_a_stale_copy(): void
    {
        $run = Run::factory()->create(['plate' => 'BRC', 'score' => 0]);
        $stale = Run::find($run->id);

        // Another request scores in the meantime.
        Run::whereKey($run->id)->update(['score' => Run::POINTS_CORRECT]);

        $stale->withLock(function () use ($stale) {
            $stale->skipPlate('DFG');
            $stale->save();
        });

        $this->assertSame(Run::POINTS_CORRECT - Run::POINTS_PENALTY, $run->fresh()->score);
    }

    public function test_an_ended_run_accepts_no_more_words_and_cannot_be_reopened(): void
    {
        Http::fake();
        $run = Run::factory()->create(['plate' => 'BRC']);

        $this->actingAs($run->user)->postJson(route('game.end', $run))->assertOk();

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'barco'])->assertStatus(409);
        $this->actingAs($run->user)->get(route('game.show', $run))->assertRedirect(route('menu.view'));
    }

    public function test_the_game_page_renders_the_server_plate(): void
    {
        $run = Run::factory()->countdown()->create(['plate' => 'BRC']);

        $this->actingAs($run->user)->get(route('game.show', $run))
            ->assertOk()
            ->assertSeeInOrder(['B', 'R', 'C'])
            ->assertSee(route('game.check-word', $run));
    }

    public function test_deleting_an_account_also_deletes_its_runs(): void
    {
        $run = Run::factory()->create();

        $this->actingAs($run->user)->delete(route('profile.delete'))->assertRedirect();

        $this->assertDatabaseCount('runs', 0);
    }
}
