<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_rankings_only_count_runs_with_at_least_one_attempted_word(): void
    {
        $viewer = User::factory()->create();
        $player = User::factory()->create(['name' => 'Jugadora']);
        Run::factory()->for($player)->create(['score' => 30, 'words_correct' => ['BARCO', 'CABRA', 'BRECA']]);
        Run::factory()->for($player)->create(['score' => -5, 'words_error' => ['BRCX']]);
        Run::factory()->for($player)->create(['score' => 999]);
        Run::factory()->create(['score' => 500]);

        $topScores = $this->actingAs($viewer)->getJson(route('scores.filter'))->assertOk()->json('topScores');

        $this->assertCount(1, $topScores);
        $this->assertSame('Jugadora', $topScores[0]['name']);
        $this->assertEquals(30, $topScores[0]['highest_score']);
        $this->assertEquals(2, $topScores[0]['total_runs']);
    }
}
