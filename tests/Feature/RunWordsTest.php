<?php

namespace Tests\Feature;

use App\Enums\LetterSets;
use App\Models\Run;
use App\Models\RunWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunWordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('words')->insert([
            ['key' => 'CAMION', 'form' => 'camión', 'lemma' => 'camión', 'source' => 'test'],
            ['key' => 'CAMIONERO', 'form' => 'camionero', 'lemma' => 'camionero', 'source' => 'test'],
        ]);
        Http::fake(['rae-api.com/*' => Http::response(['ok' => false], 404)]);
    }

    public function test_every_attempt_is_recorded_with_its_plate_reason_points_and_time(): void
    {
        $this->travelTo(now()->startOfSecond());
        $run = Run::factory()->create(['plate' => 'CMN', 'plate_dealt_at' => now()]);
        $this->travel(1500)->milliseconds();

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'Camion'])->assertJson(['success' => true]);
        $plate = $run->fresh()->plate;
        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'xyz'])->assertJson(['success' => false]);
        $this->actingAs($run->user)->postJson(route('game.change-plate', $run))->assertOk();

        $attempts = $run->runWords()->orderBy('id')->get();

        $this->assertSame(['word', 'word', 'skip'], $attempts->pluck('kind')->all());
        $this->assertSame(['CMN', $plate, $plate], $attempts->pluck('plate')->all());
        $this->assertSame([10, -5, -5], $attempts->pluck('points')->all());

        $this->assertSame('Camion', $attempts[0]->input);
        $this->assertSame('camión', $attempts[0]->display());
        $this->assertTrue($attempts[0]->valid);
        $this->assertSame(1500, $attempts[0]->elapsed_ms);

        $this->assertSame(RunWord::REASON_MISSING_LETTERS, $attempts[1]->reason);
        $this->assertSame(0, $attempts[1]->elapsed_ms, 'Timed from the plate dealt after the correct word');
    }

    public function test_rejection_reasons_and_repeated_words_are_stored(): void
    {
        $run = Run::factory()->settings(LetterSets::SIMPLE, ordered: true)->create(['plate' => 'NMC']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'camion']);
        $run->update(['plate' => 'BRC']);
        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'brcaza']);
        $run->update(['plate' => 'CMN']);
        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'camión']);
        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'CAMION'])
            ->assertJson(['success' => false, 'score' => 0]);

        $this->assertSame(
            [RunWord::REASON_WRONG_ORDER, RunWord::REASON_NOT_IN_DICTIONARY, null, RunWord::REASON_REPEATED],
            $run->runWords()->orderBy('id')->pluck('reason')->all(),
        );
        $this->assertSame([-5, -5, 10, 0], $run->runWords()->orderBy('id')->pluck('points')->all());
    }

    public function test_the_summary_shows_accents_and_the_fastest_word(): void
    {
        $run = Run::factory()->create(['ended_at' => now()]);
        $run->runWords()->createMany([
            ['kind' => 'word', 'plate' => 'CMN', 'input' => 'camionero', 'key' => 'CAMIONERO', 'form' => 'camionero', 'valid' => true, 'points' => 10, 'elapsed_ms' => 4200],
            ['kind' => 'word', 'plate' => 'CMN', 'input' => 'camion', 'key' => 'CAMION', 'form' => 'camión', 'valid' => true, 'points' => 10, 'elapsed_ms' => 1830],
            ['kind' => 'word', 'plate' => 'CMN', 'input' => 'CAMION', 'key' => 'CAMION', 'valid' => false, 'reason' => 'repeated', 'points' => 0, 'elapsed_ms' => 900],
        ]);

        $this->actingAs($run->user)->get(route('game.summary', $run))
            ->assertOk()
            ->assertSeeInOrder(['La más larga', 'camionero', 'La más rápida', 'camión, 1,8 s'])
            ->assertDontSee('line-through">camion', false);
    }

    public function test_the_backfill_copies_the_json_arrays_and_recovers_accents(): void
    {
        $run = Run::factory()->create();
        DB::table('runs')->where('id', $run->id)->update([
            'words_correct' => json_encode(['CAMION']),
            'words_error' => json_encode(['BRCX']),
            'chars_skipped' => json_encode([['M', 'T', 'R']]),
        ]);
        DB::table('run_words')->delete();

        (require database_path('migrations/2026_11_27_140100_backfill_run_words.php'))->up();

        $rows = DB::table('run_words')->where('run_id', $run->id)->orderBy('id')->get();
        $this->assertSame(['camión', null, null], $rows->pluck('form')->all());
        $this->assertSame([10, -5, -5], $rows->pluck('points')->map(fn ($points) => (int) $points)->all());
        $this->assertSame([null, null, 'MTR'], $rows->pluck('plate')->all());
    }
}
