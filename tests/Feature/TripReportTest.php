<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Services\TripReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TripReportTest extends TestCase
{
    use RefreshDatabase;

    private function playedRun(): Run
    {
        $run = Run::factory()->create(['ended_at' => now()]);
        $word = fn (string $plate, string $input, string $key, bool $valid, ?string $reason, int $points, ?int $ms, ?string $form = null) => [
            'kind' => 'word', 'plate' => $plate, 'input' => $input, 'key' => $key, 'form' => $form,
            'valid' => $valid, 'reason' => $reason, 'points' => $points, 'elapsed_ms' => $ms,
        ];

        $run->runWords()->createMany([
            $word('BRC', 'brcaza', 'BRCAZA', false, 'not_in_dictionary', -5, 900),
            $word('BRC', 'barco', 'BARCO', true, null, 10, 2100, 'barco'),
            $word('MTR', 'mtro', 'MTRO', false, 'missing_letters', -5, 700),
            $word('MTR', 'metro', 'METRO', true, null, 10, 1800, 'metro'),
            ['kind' => 'skip', 'plate' => 'HJD', 'points' => -5, 'elapsed_ms' => 5000],
            $word('PJR', 'barco', 'BARCO', false, 'repeated', 0, 1000),
            $word('PJR', 'pajaro', 'PAJARO', true, null, 10, 2600, 'pájaro'),
        ]);

        DB::table('plates')->insert([
            ['plate' => 'BRC', 'simple' => true, 'solutions' => 10, 'solutions_ordered' => 5, 'examples' => json_encode(['BARCA', 'BARCO', 'BECAR', 'BROCA', 'BRECA']), 'examples_ordered' => '[]'],
            ['plate' => 'HJD', 'simple' => true, 'solutions' => 10, 'solutions_ordered' => 5, 'examples' => json_encode(['HOJALDRE']), 'examples_ordered' => '[]'],
        ]);
        DB::table('words')->insert([
            ['key' => 'BECAR', 'form' => 'becar', 'lemma' => 'becar', 'source' => 'test'],
            ['key' => 'BROCA', 'form' => 'broca', 'lemma' => 'broca', 'source' => 'test'],
            ['key' => 'BARCA', 'form' => 'barca', 'lemma' => 'barca', 'source' => 'test'],
        ]);

        return $run;
    }

    public function test_the_report_counts_accuracy_and_times_without_repeated_words(): void
    {
        $report = app(TripReport::class)->build($this->playedRun());

        // 3 valid of 5 scored attempts; the repeated "barco" neither helps nor hurts.
        $this->assertSame(60, $report['accuracy']);
        $this->assertSame(5, $report['attempted']);
        $this->assertSame(2167, $report['averageMs']);
        $this->assertSame('metro', $report['fastest']->display());
        $this->assertSame('pájaro', $report['longest']->display());
        $this->assertSame(1, $report['skips']);
    }

    public function test_plates_group_attempts_and_suggest_unused_shortest_words(): void
    {
        $plates = app(TripReport::class)->build($this->playedRun())['plates'];

        $this->assertSame(['BRC', 'MTR', 'HJD', 'PJR'], array_column($plates, 'plate'));
        $this->assertSame(['brcaza', 'barco'], array_map(fn ($word) => $word->display(), $plates[0]['attempts']));
        // "barco" was played, so it is not suggested again; spellings come from the dictionary.
        $this->assertSame(['barca', 'becar', 'broca'], $plates[0]['examples']);
        $this->assertTrue($plates[2]['skipped']);
        $this->assertSame([], $plates[2]['attempts']);
        $this->assertSame(['hojaldre'], $plates[2]['examples']);
    }

    public function test_the_summary_page_shows_the_receipt(): void
    {
        $run = $this->playedRun();

        $this->actingAs($run->user)->get(route('game.summary', $run))
            ->assertOk()
            ->assertSeeInOrder(['60', 'de aciertos', '3 de 5 palabras'])
            ->assertSeeInOrder(['Tiempo medio por acierto', '2,2 s', 'La más larga', 'pájaro', 'La más rápida', 'metro, 1,8 s'])
            ->assertSeeInOrder(['Por qué fallaron', 'brcaza', 'No está en el diccionario', 'mtro', 'Le faltan letras de la matrícula', 'barco', 'Repetida'])
            ->assertSeeInOrder(['Matrícula a matrícula', 'BRC', 'También valían: barca, becar, broca', 'HJD', 'Pasamatrícula', 'Podías haber hecho: hojaldre'])
            ->assertDontSee('matrículas que faltan');
    }

    public function test_long_trips_fold_the_remaining_plates(): void
    {
        $run = Run::factory()->create(['ended_at' => now()]);
        foreach (['BRC', 'MTR', 'PLN', 'GST', 'DNT', 'SLM', 'PJR'] as $plate) {
            $run->runWords()->create(['kind' => 'skip', 'plate' => $plate, 'points' => -5]);
        }

        $this->actingAs($run->user)->get(route('game.summary', $run))
            ->assertOk()
            ->assertSee('Ver las 2 matrículas que faltan')
            ->assertSeeInOrder(['<details', 'SLM'], false);
    }
}
