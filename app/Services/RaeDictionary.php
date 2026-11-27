<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class RaeDictionary
{
    private const URL = 'https://rae-api.com/api/words/';

    /**
     * Whether the word exists, or null when the service could not answer.
     */
    public function exists(string $word): ?bool
    {
        try {
            $response = Http::connectTimeout(2)
                ->timeout(3)
                ->acceptJson()
                ->get(self::URL.rawurlencode(mb_strtolower($word)));
        } catch (ConnectionException) {
            return null;
        }

        if ($response->serverError()) {
            return null;
        }

        $ok = $response->json('ok');

        return is_bool($ok) ? $ok : null;
    }
}
