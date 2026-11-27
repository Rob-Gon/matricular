<?php

namespace Tests\Feature;

use App\Dictionary\RlaEsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ImportDictionaryTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURES = __DIR__.'/../Fixtures/dictionary';

    public function test_it_imports_playable_forms_with_their_lemma_and_key(): void
    {
        $this->artisan('dictionary:import', ['--path' => self::FIXTURES])->assertSuccessful();

        $forms = DB::table('words')->pluck('lemma', 'form')->all();

        $this->assertSame('útil', $forms['inútiles']);
        $this->assertSame('bonito', $forms['bonitas']);
        $this->assertArrayHasKey('comiendo', $forms);
        $this->assertSame('INUTILES', DB::table('words')->where('form', 'inútiles')->value('key'));
    }

    public function test_it_skips_enclitics_proper_nouns_and_short_forms(): void
    {
        $this->artisan('dictionary:import', ['--path' => self::FIXTURES])->assertSuccessful();

        $forms = DB::table('words')->pluck('form')->all();

        foreach (['comerlo', 'comerse', 'Abel', 'ir'] as $form) {
            $this->assertNotContains($form, $forms);
        }
    }

    public function test_reimporting_replaces_its_own_rows_but_keeps_learned_words(): void
    {
        DB::table('words')->insert(['key' => 'WIFI', 'form' => 'wifi', 'lemma' => 'wifi', 'source' => 'rae-api']);

        $this->artisan('dictionary:import', ['--path' => self::FIXTURES])->assertSuccessful();
        $count = DB::table('words')->count();
        $this->artisan('dictionary:import', ['--path' => self::FIXTURES])->assertSuccessful();

        $this->assertSame($count, DB::table('words')->count());
        $this->assertTrue(DB::table('words')->where('form', 'wifi')->exists());
    }

    public function test_a_download_with_the_wrong_checksum_is_rejected(): void
    {
        $storage = sys_get_temp_dir().'/matricular-test-'.uniqid();
        $this->app->useStoragePath($storage);
        Http::fake(['raw.githubusercontent.com/*' => Http::response('tampered')]);

        try {
            $this->expectException(RuntimeException::class);
            $this->artisan('dictionary:import');
        } finally {
            $this->assertFileDoesNotExist("{$storage}/app/dictionary/rla-es/".array_key_first(RlaEsSource::FILES));
            File::deleteDirectory($storage);
        }
    }
}
