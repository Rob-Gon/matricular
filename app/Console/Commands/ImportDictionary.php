<?php

namespace App\Console\Commands;

use App\Dictionary\HunspellExpander;
use App\Dictionary\RlaEsSource;
use App\Support\WordNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImportDictionary extends Command
{
    private const CHUNK = 2000;

    protected $signature = 'dictionary:import
        {--path= : Directory that already holds es_ES.aff and es_ES.dic (skips the download)}';

    protected $description = 'Import every playable Spanish word form from the RLA-ES Hunspell dictionary';

    public function handle(): int
    {
        $started = microtime(true);
        $directory = $this->option('path') ?: $this->download();

        $aff = File::get("{$directory}/es_ES.aff");
        $dic = File::lines("{$directory}/es_ES.dic");

        $expander = (new HunspellExpander($aff))->excluding(RlaEsSource::isEnclitic(...));

        // Re-importing replaces only this source; words learned from the RAE API are kept.
        DB::table('words')->where('source', RlaEsSource::SOURCE)->delete();

        $rows = [];
        $lemmas = 0;
        $inserted = 0;

        foreach ($dic->skip(1) as $line) {
            [$stem, $flags] = HunspellExpander::parseDicLine($line);

            // Proper nouns, acronyms and abbreviations are not game words, and neither are their forms.
            // Short lemmas stay: "ir" itself is too short to play, but "iba" and "vamos" are not.
            if (! RlaEsSource::isLowercaseWord($stem)) {
                continue;
            }

            $lemmas++;

            foreach ($expander->expand($stem, $flags) as $form) {
                if (! RlaEsSource::isPlayable($form)) {
                    continue;
                }

                $rows[] = ['key' => WordNormalizer::key($form), 'form' => $form, 'lemma' => $stem, 'source' => RlaEsSource::SOURCE];

                if (count($rows) === self::CHUNK) {
                    $inserted += DB::table('words')->insertOrIgnore($rows);
                    $rows = [];
                }
            }
        }

        $inserted += DB::table('words')->insertOrIgnore($rows);

        $this->info(sprintf('Imported %d forms from %d lemmas in %.1f s.', $inserted, $lemmas, microtime(true) - $started));

        return self::SUCCESS;
    }

    private function download(): string
    {
        $directory = storage_path('app/dictionary/rla-es');
        File::ensureDirectoryExists($directory);

        foreach (RlaEsSource::FILES as $file => $checksum) {
            $path = "{$directory}/{$file}";

            if (! File::exists($path) || hash_file('sha256', $path) !== $checksum) {
                $this->line("Downloading {$file}…");
                Http::timeout(120)->sink($path)->get(RlaEsSource::url($file))->throw();
            }

            if (hash_file('sha256', $path) !== $checksum) {
                File::delete($path);
                throw new RuntimeException("Checksum mismatch for {$file}: refusing to import an unexpected file.");
            }
        }

        return $directory;
    }
}
