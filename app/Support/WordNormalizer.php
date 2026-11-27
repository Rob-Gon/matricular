<?php

namespace App\Support;

use Normalizer;

class WordNormalizer
{
    /** Private-use character that shields Ñ while the other diacritics are stripped. */
    private const ENYE_PLACEHOLDER = "\u{E000}";

    /**
     * Lookup key for a word: uppercase, accents and diaeresis removed, Ñ kept as its own letter.
     * "camión", "CAMION" and "Camión" share the key CAMION; "niño" (NIÑO) never matches "nino" (NINO).
     */
    public static function key(string $word): string
    {
        $word = mb_strtoupper(trim($word));
        $word = str_replace('Ñ', self::ENYE_PLACEHOLDER, $word);

        // Decompose (Á → A + combining accent) and drop the combining marks.
        $decomposed = Normalizer::normalize($word, Normalizer::FORM_D);
        $word = preg_replace('/\p{Mn}+/u', '', $decomposed === false ? $word : $decomposed);

        return str_replace(self::ENYE_PLACEHOLDER, 'Ñ', (string) $word);
    }
}
