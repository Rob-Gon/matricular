<?php

namespace Tests\Feature;

use App\Enums\LetterSets;
use App\Models\Achievement;
use App\Models\Run;
use App\Models\User;
use App\Services\AchievementUnlocker;
use Database\Seeders\AchievementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementsSeeder::class);
        Http::fake(['rae-api.com/*' => Http::response(['ok' => true])]);
    }

    /**
     * @return list<string>
     */
    private function unlockedKeys(User $user): array
    {
        return $user->achievements()->pluck('key')->all();
    }

    public function test_the_seeder_syncs_the_catalogue_and_keeps_unlocks(): void
    {
        $this->assertSame(count(config('achievements')), Achievement::count());
        $this->assertTrue(Achievement::where('key', 'palindrome')->value('hidden'));

        $user = User::factory()->create();
        $user->achievements()->attach(Achievement::where('key', 'long_word')->value('id'), ['unlocked_at' => now()]);

        $this->seed(AchievementsSeeder::class);

        $this->assertSame(['long_word'], $this->unlockedKeys($user));
    }

    public function test_a_long_word_unlocks_once_and_is_returned_for_the_toast(): void
    {
        $run = Run::factory()->create(['plate' => 'BRC']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'carburaciones'])
            ->assertOk()
            ->assertJsonPath('achievements.0.key', 'long_word')
            ->assertJsonPath('achievements.0.name', 'Palabra kilométrica');

        $run->update(['plate' => 'BRC']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'barcos'])
            ->assertJsonPath('achievements', []);

        $this->assertSame(['long_word'], $this->unlockedKeys($run->user));
    }

    public function test_a_rejected_word_unlocks_nothing(): void
    {
        $run = Run::factory()->create(['plate' => 'XYZ']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'carburaciones'])
            ->assertJsonPath('achievements', []);
    }

    public function test_a_palindrome_unlocks_the_hidden_achievement(): void
    {
        $run = Run::factory()->create(['plate' => 'RCN']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'reconocer'])
            ->assertJsonPath('achievements.0.key', 'palindrome');
    }

    public function test_score_milestones(): void
    {
        $easy = Run::factory()->create(['plate' => 'BRC', 'score' => 495]);
        $hard = Run::factory()->settings(LetterSets::FULL, ordered: true)->create(['plate' => 'BRC', 'score' => 245]);
        $unordered = Run::factory()->settings(LetterSets::FULL)->create(['plate' => 'BRC', 'score' => 245]);

        $this->actingAs($easy->user)->postJson(route('game.check-word', $easy), ['word' => 'barco'])
            ->assertJsonPath('achievements.0.key', 'high_score');
        $this->actingAs($hard->user)->postJson(route('game.check-word', $hard), ['word' => 'barco'])
            ->assertJsonPath('achievements.0.key', 'hard_high_score');
        $this->actingAs($unordered->user)->postJson(route('game.check-word', $unordered), ['word' => 'barco'])
            ->assertJsonPath('achievements', []);
    }

    public function test_ending_the_first_game_with_a_word_unlocks_first_game(): void
    {
        $empty = Run::factory()->create();
        $played = Run::factory()->attempts(invalid: ['BRCX'])->create();

        $this->actingAs($empty->user)->postJson(route('game.end', $empty))->assertJsonPath('achievements', []);
        $this->actingAs($played->user)->postJson(route('game.end', $played))->assertJsonPath('achievements.0.key', 'first_game');

        // Ending again does not evaluate anything.
        $this->actingAs($played->user)->postJson(route('game.end', $played))->assertJsonPath('achievements', []);
    }

    public function test_the_summary_closes_a_countdown_that_ran_out_with_the_tab_closed(): void
    {
        $run = Run::factory()->countdown()->attempts(invalid: ['BRCX'])->create(['ends_at' => now()->subMinute()]);

        $this->actingAs($run->user)->get(route('game.summary', $run))
            ->assertOk()
            ->assertSee('Logros de esta partida')
            ->assertSee(Achievement::where('key', 'first_game')->value('name'));

        $this->assertNotNull($run->fresh()->ended_at);
        $this->assertSame(['first_game'], $this->unlockedKeys($run->user));
    }

    public function test_five_games_in_ten_minutes(): void
    {
        $user = User::factory()->create();
        Run::factory()->for($user)->count(3)->attempts(invalid: ['X'])->create(['created_at' => now()->subMinutes(5)]);
        Run::factory()->for($user)->attempts(invalid: ['X'])->create(['created_at' => now()->subMinutes(30)]);
        $fourth = Run::factory()->for($user)->attempts(invalid: ['X'])->create();

        $this->actingAs($user)->postJson(route('game.end', $fourth))
            ->assertJsonMissing(['key' => 'quick_succession']);

        $fifth = Run::factory()->for($user)->attempts(invalid: ['X'])->create();

        $this->actingAs($user)->postJson(route('game.end', $fifth))
            ->assertJsonFragment(['key' => 'quick_succession']);
    }

    public function test_twenty_five_games_in_a_day(): void
    {
        $user = User::factory()->create();
        Run::factory()->for($user)->count(24)->attempts(invalid: ['X'])->create(['created_at' => now()->startOfDay()->addMinute()]);
        $last = Run::factory()->for($user)->attempts(invalid: ['X'])->create();

        $this->actingAs($user)->postJson(route('game.end', $last))
            ->assertJsonFragment(['key' => 'touch_grass']);
    }

    public function test_platinum_unlocks_with_the_last_achievement(): void
    {
        $run = Run::factory()->create(['plate' => 'BRC']);
        $others = Achievement::whereNotIn('key', ['platinum', 'long_word'])->pluck('id');
        $run->user->achievements()->attach($others, ['unlocked_at' => now()]);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'carburaciones'])
            ->assertJsonPath('achievements.0.key', 'long_word')
            ->assertJsonPath('achievements.1.key', 'platinum');
    }

    public function test_checking_a_word_costs_one_catalogue_query_when_nothing_unlocks(): void
    {
        $run = Run::factory()->create(['plate' => 'BRC']);
        $unlocker = app(AchievementUnlocker::class);

        $this->expectsDatabaseQueryCount(1);

        $unlocker->afterWord($run, 'BARCO');
    }

    public function test_the_achievements_page_hides_locked_hidden_achievements(): void
    {
        $user = User::factory()->create();
        $user->achievements()->attach(Achievement::where('key', 'long_word')->value('id'), ['unlocked_at' => now()]);

        $this->actingAs($user)->get(route('achievements.view'))
            ->assertOk()
            ->assertSee('Has desbloqueado 1 de '.Achievement::count())
            ->assertSee('Palabra kilométrica')
            ->assertSee('???')
            ->assertDontSee('Acierta una palabra que se lea igual al revés');
    }
}
