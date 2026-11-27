<?php

namespace App\Services;

use App\Support\WordNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RaeDictionary
{
    private const URL = 'https://rae-api.com/api/words/';

    private const CACHE_DAYS = 30;

    /**
     * Whether the word exists, or null when the service could not answer.
     * Accents are optional for the player: the API only matches the accented form, but its
     * NOT_FOUND answer suggests it ("camion" → ["camión"]), so a suggestion with the same key counts.
     */
    public function exists(string $word): ?bool
    {
        $key = WordNormalizer::key($word);
        $cacheKey = "dictionary:rae:{$key}";

        $cached = Cache::get($cacheKey);
        if (is_bool($cached)) {
            return $cached;
        }

        $exists = $this->lookup($word, $key);

        // Outages (null) are not cached, so the word is checked again on the next try.
        if ($exists !== null) {
            Cache::put($cacheKey, $exists, now()->addDays(self::CACHE_DAYS));
        }

        return $exists;
    }

    private function lookup(string $word, string $key): ?bool
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

        $ok = $response->json('ok');

        if ($ok === true) {
            return true;
        }

        if ($ok !== false) {
            return null;
        }

        $suggestions = $response->json('suggestions');

        return is_array($suggestions) && collect($suggestions)
            ->filter(fn ($suggestion) => is_string($suggestion))
            ->contains(fn (string $suggestion) => WordNormalizer::key($suggestion) === $key);
    }
}
