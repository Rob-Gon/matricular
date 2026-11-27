<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Services\Dictionary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordNormalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The real API only knows the accented form and answers NOT_FOUND with suggestions otherwise.
     *
     * @param  list<string>  $suggestions
     */
    private function fakeNotFound(array $suggestions): void
    {
        Http::fake(['rae-api.com/*' => Http::response(['ok' => false, 'error' => 'NOT_FOUND', 'suggestions' => $suggestions], 404)]);
    }

    public function test_a_word_typed_without_accents_is_accepted(): void
    {
        $this->fakeNotFound(['camión']);
        $run = Run::factory()->create(['plate' => 'CMN']);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'CAMION'])
            ->assertOk()
            ->assertJson(['success' => true, 'score' => Run::POINTS_CORRECT]);

        $this->assertSame(['CAMION'], $run->runWords()->pluck('key')->all());
    }

    public function test_the_same_word_with_and_without_accents_counts_once(): void
    {
        Http::fake(['rae-api.com/*' => Http::response(['ok' => true])]);
        $run = Run::factory()->attempts(valid: ['CAMION'])->create(['plate' => 'CMN', 'score' => 10]);

        $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => 'camión'])
            ->assertJson(['success' => false, 'score' => 10]);
    }

    public function test_enye_is_not_optional(): void
    {
        $this->fakeNotFound(['niño', 'vino', 'nido']);

        $this->assertFalse(app(Dictionary::class)->exists('nino'));
    }

    public function test_diaeresis_is_optional(): void
    {
        $this->fakeNotFound(['pingüino']);

        $this->assertTrue(app(Dictionary::class)->exists('pinguino'));
    }

    public function test_answers_are_cached_by_key(): void
    {
        Http::fake(['rae-api.com/*' => Http::response(['ok' => true])]);
        $dictionary = app(Dictionary::class);

        $this->assertTrue($dictionary->exists('camión'));
        $this->assertTrue($dictionary->exists('CAMION'));

        Http::assertSentCount(1);
    }

    public function test_outages_are_not_cached(): void
    {
        Http::fakeSequence('rae-api.com/*')
            ->pushFailedConnection()
            ->push(['ok' => true]);
        $dictionary = app(Dictionary::class);

        $this->assertNull($dictionary->exists('barco'));
        $this->assertTrue($dictionary->exists('barco'));
    }

    public function test_words_with_digits_or_spaces_are_rejected_before_scoring(): void
    {
        Http::fake();
        $run = Run::factory()->create(['plate' => 'BRC']);

        foreach (['barco1', 'bar co', 'barco!'] as $word) {
            $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => $word])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['word' => 'La palabra solo puede contener letras.']);
        }

        Http::assertNothingSent();
        $this->assertSame(0, $run->fresh()->score);
    }
}
