<?php

namespace Tests\Feature;

use App\Enums\LetterSets;
use App\Services\PlateGenerator;
use App\Support\WordNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $forms
     */
    private function addWords(array $forms): void
    {
        DB::table('words')->insert(array_map(
            fn (string $form) => ['key' => WordNormalizer::key($form), 'form' => $form, 'lemma' => $form, 'source' => 'rla-es'],
            $forms,
        ));
    }

    /**
     * @return array{simple: bool, solutions: int, solutions_ordered: int}
     */
    private function plate(string $plate): array
    {
        return (array) DB::table('plates')->where('plate', $plate)->first(['simple', 'solutions', 'solutions_ordered']);
    }

    public function test_it_counts_solutions_in_any_order_and_in_plate_order(): void
    {
        // "barco" and "cabra" (and "cabrá", same key) contain B, R, C; only "barco" has them in B-R-C order.
        $this->addWords(['barco', 'cabra', 'cabrá', 'sol']);

        $this->artisan('dictionary:plates')->assertSuccessful();

        $this->assertDatabaseCount('plates', 8000);
        $this->assertEquals(['simple' => 1, 'solutions' => 2, 'solutions_ordered' => 1], $this->plate('BRC'));
        $this->assertEquals(['simple' => 1, 'solutions' => 2, 'solutions_ordered' => 1], $this->plate('CBR'));
        $this->assertEquals(['simple' => 0, 'solutions' => 0, 'solutions_ordered' => 0], $this->plate('XYZ'));
    }

    public function test_it_keeps_the_shortest_headwords_as_examples(): void
    {
        $this->addWords(['barco', 'cabra', 'bracero', 'abrochar', 'brcx']);
        // A conjugation (not a headword) and a word Wiktionary does not list are never suggested.
        DB::table('words')->insert(['key' => 'ABROCHA', 'form' => 'abrocha', 'lemma' => 'abrochar', 'source' => 'rla-es']);
        DB::table('word_categories')->insert(array_map(
            fn (string $word) => ['word' => $word, 'pos' => 'noun', 'source' => 'test'],
            ['barco', 'cabra', 'bracero', 'abrochar'],
        ));

        $this->artisan('dictionary:plates')->assertSuccessful();

        $examples = DB::table('plates')->where('plate', 'BRC')->first(['examples', 'examples_ordered']);
        $this->assertSame(['BARCO', 'CABRA', 'BRACERO', 'ABROCHAR'], json_decode($examples->examples));
        $this->assertSame(['BARCO', 'BRACERO', 'ABROCHAR'], json_decode($examples->examples_ordered));
    }

    public function test_repeated_plate_letters_need_repeated_word_letters(): void
    {
        $this->addWords(['sesos', 'seso']);

        $this->artisan('dictionary:plates')->assertSuccessful();

        // Only "sesos" has three S; "seso" has two.
        $this->assertSame(1, (int) $this->plate('SSS')['solutions']);
    }

    public function test_the_generator_only_deals_plates_with_enough_solutions(): void
    {
        DB::table('plates')->insert([
            ['plate' => 'BRC', 'simple' => true, 'solutions' => PlateGenerator::MIN_SOLUTIONS, 'solutions_ordered' => 0],
            ['plate' => 'XYZ', 'simple' => false, 'solutions' => PlateGenerator::MIN_SOLUTIONS, 'solutions_ordered' => PlateGenerator::MIN_SOLUTIONS],
            ['plate' => 'DFG', 'simple' => true, 'solutions' => PlateGenerator::MIN_SOLUTIONS - 1, 'solutions_ordered' => 99],
        ]);
        $generator = app(PlateGenerator::class);

        // Simple letters skip XYZ; ordered games count solutions in plate order.
        $this->assertSame('BRC', $generator->generate(LetterSets::SIMPLE, false));
        $this->assertContains($generator->generate(LetterSets::FULL, false), ['BRC', 'XYZ']);
        $this->assertSame('DFG', $generator->generate(LetterSets::SIMPLE, true));
        $this->assertContains($generator->generate(LetterSets::FULL, true), ['XYZ', 'DFG']);
    }

    public function test_the_generator_falls_back_to_random_letters_without_the_plates_table(): void
    {
        $this->assertMatchesRegularExpression('/^[BCDFGHJLMNPRST]{3}$/', app(PlateGenerator::class)->generate(LetterSets::SIMPLE, false));
    }

    public function test_rebuilding_refreshes_the_generator_cache(): void
    {
        $generator = app(PlateGenerator::class);
        $generator->generate(LetterSets::SIMPLE, false);

        $this->addWords(array_map(fn (int $i) => 'barco'.str_repeat('a', $i), range(1, PlateGenerator::MIN_SOLUTIONS)));
        $this->artisan('dictionary:plates')->assertSuccessful();

        // Any arrangement of B, R and C now has enough solutions.
        $letters = str_split($generator->generate(LetterSets::SIMPLE, false));
        sort($letters);
        $this->assertSame(['B', 'C', 'R'], $letters);
    }
}
