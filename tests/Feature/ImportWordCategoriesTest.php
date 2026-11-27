<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportWordCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = __DIR__.'/../Fixtures/dictionary/wiktionary.jsonl';

    public function test_it_imports_spanish_categories_per_written_word(): void
    {
        $this->artisan('dictionary:categories', ['--path' => self::FIXTURE])->assertSuccessful();

        $categories = DB::table('word_categories')->orderBy('pos')->get(['word', 'pos'])
            ->map(fn ($row) => "{$row->word}:{$row->pos}")
            ->sort()->values()->all();

        $this->assertSame(['camiones:noun', 'camión:noun', 'comimos:verb', 'japonés:adj', 'japonés:noun'], $categories);
    }

    public function test_reimporting_does_not_duplicate_rows(): void
    {
        $this->artisan('dictionary:categories', ['--path' => self::FIXTURE])->assertSuccessful();
        $this->artisan('dictionary:categories', ['--path' => self::FIXTURE])->assertSuccessful();

        $this->assertSame(5, DB::table('word_categories')->count());
    }

    public function test_the_credits_page_attributes_the_sources(): void
    {
        $this->get(route('credits.view'))
            ->assertOk()
            ->assertSee('RLA-ES')
            ->assertSee('Creative Commons Atribución-CompartirIgual 4.0')
            ->assertSee('Wiktextract');
    }
}
