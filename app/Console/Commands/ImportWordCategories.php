<?php

namespace App\Console\Commands;

use App\Dictionary\RlaEsSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImportWordCategories extends Command
{
    public const SOURCE = 'wiktionary';

    /** Spanish Wiktionary extracted by Wiktextract. Content: CC BY-SA 4.0 (credited on /creditos). */
    private const URL = 'https://kaikki.org/eswiktionary/raw-wiktextract-data.jsonl.gz';

    /** Categories the game can use; proper names, phrases, proverbs, affixes and abbreviations are skipped. */
    private const CATEGORIES = [
        'noun', 'adj', 'verb', 'adv', 'participle', 'pron', 'prep', 'conj', 'intj', 'num', 'article', 'contraction',
    ];

    private const CHUNK = 2000;

    protected $signature = 'dictionary:categories
        {--path= : Local Wiktextract JSONL file, gzipped or plain (skips the download)}';

    protected $description = 'Import parts of speech for Spanish words from Spanish Wiktionary (kaikki.org)';

    public function handle(): int
    {
        $started = microtime(true);
        $path = $this->option('path') ?: $this->download();

        // gzopen also reads uncompressed files, so tests can use a plain JSONL fixture.
        $file = gzopen($path, 'rb');
        if ($file === false) {
            throw new RuntimeException("Cannot open {$path}.");
        }

        DB::table('word_categories')->where('source', self::SOURCE)->delete();

        $rows = [];
        $inserted = 0;

        try {
            while (($line = gzgets($file)) !== false) {
                // Cheap pre-filter: most lines are other languages and need no JSON decoding.
                if (! str_contains($line, '"lang_code": "es"')) {
                    continue;
                }

                $entry = json_decode($line, true);
                $word = $entry['word'] ?? null;
                $pos = $entry['pos'] ?? null;

                if (($entry['lang_code'] ?? null) !== 'es'
                    || ! is_string($word) || ! is_string($pos)
                    || ! in_array($pos, self::CATEGORIES, true)
                    || mb_strlen($word) > 50 || ! RlaEsSource::isLowercaseWord($word)) {
                    continue;
                }

                $rows[] = ['word' => $word, 'pos' => $pos, 'source' => self::SOURCE];

                if (count($rows) === self::CHUNK) {
                    $inserted += DB::table('word_categories')->insertOrIgnore($rows);
                    $rows = [];
                }
            }
        } finally {
            gzclose($file);
        }

        $inserted += DB::table('word_categories')->insertOrIgnore($rows);

        $this->info(sprintf('Imported %d word categories in %.1f s.', $inserted, microtime(true) - $started));

        return self::SUCCESS;
    }

    private function download(): string
    {
        $directory = storage_path('app/dictionary/wiktionary');
        File::ensureDirectoryExists($directory);
        $path = "{$directory}/raw-wiktextract-data.jsonl.gz";

        // The extract is rebuilt monthly, so it cannot be pinned by checksum: re-download when older than a week.
        if (! File::exists($path) || File::lastModified($path) < now()->subWeek()->timestamp) {
            $this->line('Downloading the Spanish Wiktionary extract (~100 MB)…');
            Http::timeout(600)->sink($path)->get(self::URL)->throw();
        }

        return $path;
    }
}
