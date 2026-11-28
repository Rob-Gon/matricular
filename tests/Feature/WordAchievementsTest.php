<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Models\User;
use App\Support\WordNormalizer;
use Database\Seeders\AchievementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WordAchievementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AchievementsSeeder::class);
        Http::fake(['rae-api.com/*' => Http::response(['ok' => false], 404)]);
    }

    /**
     * @param  array<string, list<string>>  $words  form => parts of speech
     */
    private function addWords(array $words): void
    {
        foreach ($words as $form => $categories) {
            DB::table('words')->insert(['key' => WordNormalizer::key($form), 'form' => $form, 'lemma' => $form, 'source' => 'test']);
            foreach ($categories as $pos) {
                DB::table('word_categories')->insert(['word' => $form, 'pos' => $pos, 'source' => 'test']);
            }
        }
    }

    private function play(Run $run, string $word, string $plate): TestResponse
    {
        // Straight to the database: the in-memory model may already hold this plate while the server dealt another.
        Run::whereKey($run->id)->update(['plate' => $plate]);

        return $this->actingAs($run->user)->postJson(route('game.check-word', $run), ['word' => $word]);
    }

    /**
     * @return list<string>
     */
    private function unlocked(User $user): array
    {
        return $user->achievements()->pluck('key')->all();
    }

    public function test_accepted_words_store_their_parts_of_speech(): void
    {
        $this->addWords(['criba' => ['noun', 'verb']]);
        $run = Run::factory()->create();

        $this->play($run, 'criba', 'CRB')->assertJson(['success' => true]);

        $this->assertSame('noun,verb', $run->runWords()->value('categories'));
    }

    public function test_ten_nouns_in_one_run_unlock_sustantivame_esta(): void
    {
        $nouns = ['barco', 'barca', 'broca', 'brecha', 'cabra', 'cobre', 'corbata', 'cerebro', 'bracero', 'carbón'];
        $this->addWords(array_fill_keys($nouns, ['noun']));
        $run = Run::factory()->create();

        foreach (array_slice($nouns, 0, 9) as $noun) {
            $this->play($run, $noun, 'BRC')->assertJson(['success' => true]);
        }
        $this->assertNotContains('nouns', $this->unlocked($run->user));

        $this->play($run, 'carbón', 'BRC')->assertJsonFragment(['key' => 'nouns']);
    }

    public function test_abro_hilo_needs_a_noun_an_adjective_and_a_verb_in_a_row(): void
    {
        $this->addWords(['barco' => ['noun'], 'bravo' => ['adj'], 'cobrar' => ['verb'], 'brocal' => ['noun']]);
        $run = Run::factory()->create();

        $this->play($run, 'barco', 'BRC');
        $this->play($run, 'bravo', 'BRV');
        $this->play($run, 'brcx', 'BRC'); // a miss breaks the streak
        $this->play($run, 'cobrar', 'BRC')->assertJsonMissing(['key' => 'abro_hilo']);

        $this->play($run, 'brocal', 'BRC');
        $this->play($run, 'bravo', 'BRV')->assertJsonMissing(['key' => 'abro_hilo']); // repeated: not accepted

        $runTwo = Run::factory()->for($run->user)->create();
        $this->play($runTwo, 'cobrar', 'BRC');
        $this->play($runTwo, 'barco', 'BRC');
        $this->play($runTwo, 'bravo', 'BRV')->assertJsonFragment(['key' => 'abro_hilo']);
    }

    public function test_vowel_games_unlock_murciegalo_and_abracadabra(): void
    {
        $this->addWords(['murciélago' => ['noun'], 'abracadabra' => ['noun'], 'barbacana' => ['noun']]);
        $run = Run::factory()->create();

        $this->play($run, 'murcielago', 'MRC')->assertJsonFragment(['key' => 'panvocalic']);
        $this->play($run, 'abracadabra', 'BRC')->assertJsonFragment(['key' => 'monovocalic']);
        $this->assertSame(
            'Acierta una palabra con una sola vocal repetida al menos cuatro veces. La técnica mítica de El Menor',
            DB::table('achievements')->where('key', 'monovocalic')->value('description'),
        );
    }

    public function test_trap_words_are_refused_penalized_and_unlock_their_hidden_achievement(): void
    {
        // Even when the dictionary has it, "electroencefalografista" never counts.
        $this->addWords(['electroencefalografista' => ['noun']]);
        $run = Run::factory()->create();

        $this->play($run, 'pneumonoultramicroscopicsilicovolcanoconiosis', 'BRC')
            ->assertJson(['success' => false, 'score' => -5])
            ->assertJsonFragment(['key' => 'trap_pneumono', 'name' => 'Buen intento, perro']);
        $this->play($run, 'Electroencefalografista', 'LCT')
            ->assertJson(['success' => false, 'score' => -10])
            ->assertJsonFragment(['key' => 'trap_electro', 'name' => 'Raetita']);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('words', ['key' => 'PNEUMONOULTRAMICROSCOPICSILICOVOLCANOCONIOSIS']);
        $this->assertSame(['trap', 'trap'], $run->runWords()->pluck('reason')->all());
    }

    public function test_ending_a_run_below_zero_unlocks_the_hidden_negative_score_achievement(): void
    {
        $positive = Run::factory()->attempts(invalid: ['BRCX'])->create(['score' => 5]);
        $negative = Run::factory()->attempts(invalid: ['BRCX'])->create(['score' => -5]);

        $this->actingAs($positive->user)->postJson(route('game.end', $positive))->assertJsonMissing(['key' => 'negative_score']);
        $this->actingAs($negative->user)->postJson(route('game.end', $negative))
            ->assertJsonFragment(['key' => 'negative_score', 'name' => 'Más malo que pegarle a un padre']);
    }

    public function test_the_palindrome_is_now_called_se_es_o_no_se_es(): void
    {
        $this->assertSame('Se es o no se es', DB::table('achievements')->where('key', 'palindrome')->value('name'));
    }
}
