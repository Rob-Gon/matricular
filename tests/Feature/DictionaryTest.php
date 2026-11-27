<?php

namespace Tests\Feature;

use App\Services\Dictionary;
use App\Support\WordNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DictionaryTest extends TestCase
{
    use RefreshDatabase;

    private function addWord(string $form, string $lemma, string $source = 'rla-es'): void
    {
        DB::table('words')->insert(['key' => WordNormalizer::key($form), 'form' => $form, 'lemma' => $lemma, 'source' => $source]);
    }

    /**
     * @param  list<array{lemma: string, senses: list<array{category: string}>}>  $meanings
     */
    private function fakeRaeEntry(string $word, array $meanings): void
    {
        Http::fake(['rae-api.com/*' => Http::response(['ok' => true, 'data' => ['word' => $word, 'meanings' => $meanings]])]);
    }

    public function test_local_words_are_found_without_calling_the_api(): void
    {
        Http::fake();
        $this->addWord('camiones', 'camión');

        $this->assertTrue(app(Dictionary::class)->exists('CAMIONES'));

        Http::assertNothingSent();
    }

    public function test_words_confirmed_by_the_api_are_learned_with_lemma_and_categories(): void
    {
        $this->fakeRaeEntry('wifis', [['lemma' => 'wifi', 'senses' => [['category' => 'noun'], ['category' => 'adjective']]]]);
        $dictionary = app(Dictionary::class);

        $this->assertTrue($dictionary->exists('wifis'));
        $this->assertTrue($dictionary->exists('WIFIS'));

        Http::assertSentCount(1);
        $this->assertDatabaseHas('words', ['key' => 'WIFIS', 'form' => 'wifis', 'lemma' => 'wifi', 'source' => 'rae-api']);
        $this->assertSame(['adj', 'noun'], DB::table('word_categories')->where('word', 'wifi')->orderBy('pos')->pluck('pos')->all());
    }

    public function test_a_word_found_through_a_suggestion_is_learned_with_its_accents(): void
    {
        Http::fake(['rae-api.com/*' => Http::response(['ok' => false, 'error' => 'NOT_FOUND', 'suggestions' => ['camión']], 404)]);

        $this->assertTrue(app(Dictionary::class)->exists('camion'));

        $this->assertDatabaseHas('words', ['key' => 'CAMION', 'form' => 'camión', 'lemma' => 'camión']);
    }

    public function test_rejected_and_unanswered_words_are_not_learned(): void
    {
        Http::fakeSequence('rae-api.com/*')
            ->push(['ok' => false, 'error' => 'NOT_FOUND', 'suggestions' => ['barco']], 404)
            ->pushFailedConnection();
        $dictionary = app(Dictionary::class);

        $this->assertFalse($dictionary->exists('brcx'));
        $this->assertNull($dictionary->exists('zarco'));

        $this->assertDatabaseCount('words', 0);
    }

    public function test_an_api_headword_that_does_not_match_the_word_is_not_learned(): void
    {
        $this->fakeRaeEntry('a bordo', [['lemma' => 'a bordo', 'senses' => [['category' => 'adverb']]]]);

        $this->assertTrue(app(Dictionary::class)->exists('abordo'));

        $this->assertDatabaseCount('words', 0);
    }

    public function test_local_lookups_stay_well_under_five_milliseconds(): void
    {
        Http::fake();
        $rows = [];
        for ($i = 0; $i < 5000; $i++) {
            $form = 'palabra'.str_repeat('a', $i % 20).base_convert((string) $i, 10, 26);
            $rows[] = ['key' => WordNormalizer::key($form), 'form' => $form, 'lemma' => $form, 'source' => 'rla-es'];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('words')->insert($chunk);
        }

        $dictionary = app(Dictionary::class);
        $times = [];
        foreach (array_slice($rows, 0, 300) as $row) {
            $started = hrtime(true);
            $dictionary->exists($row['form']);
            $times[] = (hrtime(true) - $started) / 1e6;
        }
        sort($times);

        $this->assertLessThan(5, $times[(int) floor(count($times) * 0.95)]);
        Http::assertNothingSent();
    }
}
