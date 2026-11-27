<?php

namespace App\Services;

use App\Support\WordNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Unofficial RAE dictionary API (rae-api.com). Only used when the local dictionary misses.
 *
 * @phpstan-type RaeEntry array{form: string, lemma: string, categories: list<string>}
 */
class RaeDictionary
{
    private const URL = 'https://rae-api.com/api/words/';

    private const CACHE_DAYS = 30;

    /** API category names → our part-of-speech codes (same as the Wiktionary import). */
    private const CATEGORIES = [
        'noun' => 'noun', 'verb' => 'verb', 'adjective' => 'adj', 'adverb' => 'adv', 'pronoun' => 'pron',
        'preposition' => 'prep', 'conjunction' => 'conj', 'interjection' => 'intj', 'article' => 'article',
    ];

    /**
     * The entry for the word, false when it does not exist, or null when the service could not answer.
     * Accents are optional for the player: the API only matches the accented form, but its
     * NOT_FOUND answer suggests it ("camion" → ["camión"]), so a suggestion with the same key counts.
     *
     * @return RaeEntry|false|null
     */
    public function lookup(string $word): array|false|null
    {
        $key = WordNormalizer::key($word);
        $cacheKey = "dictionary:rae:v2:{$key}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $entry = $this->fetch($word, $key);

        // Outages (null) are not cached, so the word is checked again on the next try.
        if ($entry !== null) {
            Cache::put($cacheKey, $entry, now()->addDays(self::CACHE_DAYS));
        }

        return $entry;
    }

    /**
     * @return RaeEntry|false|null
     */
    private function fetch(string $word, string $key): array|false|null
    {
        try {
            $response = Http::connectTimeout(2)
                ->timeout(3)
                ->acceptJson()
                ->get(self::URL.rawurlencode(mb_strtolower(trim($word))));
        } catch (ConnectionException) {
            return null;
        }

        if ($response->serverError()) {
            return null;
        }

        return match ($response->json('ok')) {
            true => $this->entry($response),
            false => $this->suggestedEntry($response, $key),
            default => null,
        };
    }

    /**
     * @return RaeEntry
     */
    private function entry(Response $response): array
    {
        $form = (string) $response->json('data.word');
        $meanings = collect($response->json('data.meanings') ?? []);

        $categories = $meanings
            ->flatMap(fn ($meaning) => collect($meaning['senses'] ?? [])->pluck('category'))
            ->map(fn ($category) => self::CATEGORIES[$category] ?? null)
            ->filter()->unique()->values()->all();

        return [
            'form' => $form,
            'lemma' => (string) ($meanings->first()['lemma'] ?? $form),
            'categories' => $categories,
        ];
    }

    /**
     * @return RaeEntry|false
     */
    private function suggestedEntry(Response $response, string $key): array|false
    {
        $suggestion = collect($response->json('suggestions') ?? [])
            ->filter(fn ($suggestion) => is_string($suggestion))
            ->first(fn (string $suggestion) => WordNormalizer::key($suggestion) === $key);

        // The suggestion is the dictionary headword itself, so it is its own lemma; categories are unknown.
        return $suggestion === null ? false : ['form' => $suggestion, 'lemma' => $suggestion, 'categories' => []];
    }
}
