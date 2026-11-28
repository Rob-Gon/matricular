<?php

namespace Tests\Feature;

use App\Enums\CountdownModes;
use App\Enums\GameModes;
use App\Enums\LetterSets;
use App\Models\Run;
use App\Models\User;
use Database\Seeders\AchievementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomCountdownTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(?int $minutes): array
    {
        return [
            'mode' => GameModes::CONTRARELOJ->value,
            'letter_set' => LetterSets::SIMPLE->value,
            'ordered' => '0',
            'countdown_time' => CountdownModes::PERSONALIZADO->value,
            'custom_minutes' => $minutes,
        ];
    }

    public function test_a_custom_countdown_lasts_the_chosen_minutes(): void
    {
        $this->actingAs(User::factory()->create())->post(route('game.start'), $this->payload(15));

        $run = Run::sole();
        $this->assertSame(900, $run->countdown_seconds);
        $this->assertEqualsWithDelta(now()->addMinutes(15)->timestamp, $run->ends_at->timestamp, 2);
        $this->assertSame('Contrarreloj personalizado, 15 min', $run->settingsLabels()[0]);

        $this->actingAs($run->user)->get(route('game.show', $run))->assertOk()->assertSee('15:00');
    }

    public function test_custom_minutes_must_be_between_one_and_sixty(): void
    {
        $user = User::factory()->create();

        foreach ([null, 0, 61] as $minutes) {
            $this->actingAs($user)->post(route('game.start'), $this->payload($minutes))
                ->assertRedirect(route('game.setup'))
                ->assertSessionHas('error', 'Elige una duración personalizada de 1 a 60 minutos.');
        }

        $this->actingAs($user)->post(route('game.start'), $this->payload(60))->assertRedirect();
        $this->assertSame(3600, Run::sole()->countdown_seconds);
    }

    public function test_preset_countdowns_store_their_length_too(): void
    {
        $this->actingAs(User::factory()->create())->post(route('game.start'), [
            ...$this->payload(null),
            'countdown_time' => CountdownModes::DOS_MINUTOS->value,
        ]);

        $this->assertSame(120, Run::sole()->countdown_seconds);
    }

    public function test_custom_runs_do_not_count_for_the_500_points_achievement(): void
    {
        $this->seed(AchievementsSeeder::class);
        Http::fake(['rae-api.com/*' => Http::response(['ok' => true])]);
        $run = Run::factory()->customCountdown(60)->create(['plate' => 'BRC', 'score' => 495]);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'barco'])
            ->assertJson(['success' => true, 'score' => 505])
            ->assertJsonMissing(['key' => 'high_score']);
    }

    public function test_custom_runs_only_rank_under_their_own_filter(): void
    {
        $custom = Run::factory()->customCountdown(60)->attempts(valid: ['BARCO'])->create(['score' => 900]);
        $preset = Run::factory()->countdown()->attempts(valid: ['BARCO'])->create(['score' => 40]);

        $names = fn (array $query) => $this->actingAs($preset->user)
            ->get(route('scores.view', $query))
            ->viewData('topRuns')
            ->pluck('user.name')
            ->all();

        $this->assertSame([$preset->user->name], $names([]));
        $this->assertSame([$preset->user->name], $names(['mode' => 'Contrareloj']));
        $this->assertSame([$custom->user->name], $names(['mode' => 'Contrareloj', 'time' => 'Personalizado']));
    }

    public function test_playing_again_keeps_the_custom_length(): void
    {
        $run = Run::factory()->customCountdown(25)->create(['ended_at' => now()]);

        $this->actingAs($run->user)->get(route('game.summary', $run))
            ->assertOk()
            ->assertSee('name="custom_minutes" value="25"', false);
    }
}
