<?php

namespace App\Services;

use App\Support\WordNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * The game's dictionary: the local words table first (one indexed lookup), the RAE API as a
 * fallback. Words the API confirms are stored, so the local dictionary learns from play.
 */
class Dictionary
{
    public const LEARNED_SOURCE = 'rae-api';

    public function __construct(private RaeDictionary $rae) {}

    /**
     * Whether the word exists, or null when it is unknown locally and the API could not answer.
     */
    public function exists(string $word): ?bool
    {
        $key = WordNormalizer::key($word);

        if (DB::table('words')->where('key', $key)->exists()) {
            return true;
        }

        $entry = $this->rae->lookup($word);

        if (is_array($entry)) {
            $this->learn($key, $entry);
        }

        return $entry === null ? null : $entry !== false;
    }

    /**
     * Stores only the word, its lemma and categories (never definitions).
     *
     * @param  array{form: string, lemma: string, categories: list<string>}  $entry
     */
    private function learn(string $key, array $entry): void
    {
        $form = mb_strtolower($entry['form']);
        $lemma = mb_strtolower($entry['lemma']);

        // The API may return a multi-word or otherwise unplayable headword; keep the table clean.
        if (WordNormalizer::key($form) !== $key || mb_strlen($form) > 50 || mb_strlen($lemma) > 50) {
            return;
        }

        DB::table('words')->insertOrIgnore([
            'key' => $key, 'form' => $form, 'lemma' => $lemma, 'source' => self::LEARNED_SOURCE,
        ]);

        DB::table('word_categories')->insertOrIgnore(array_map(
            fn (string $pos) => ['word' => $lemma, 'pos' => $pos, 'source' => self::LEARNED_SOURCE],
            $entry['categories'],
        ));
    }
}
