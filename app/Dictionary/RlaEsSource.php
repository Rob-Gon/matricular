<?php

namespace App\Dictionary;

/**
 * RLA-ES Spanish (Spain) Hunspell dictionary, v2.9, as shipped by LibreOffice.
 * License: GPL 3+, LGPL 3+ or MPL 1.1+ (https://github.com/sbosio/rla-es).
 * Pinned to a commit and checksums so the import is reproducible; update all three together.
 */
class RlaEsSource
{
    public const SOURCE = 'rla-es';

    private const COMMIT = '762abe74008b94b2ff06db6f4024b59a8254c467';

    private const BASE_URL = 'https://raw.githubusercontent.com/LibreOffice/dictionaries/'.self::COMMIT.'/es/';

    /** @var array<string, string> file name => sha256 */
    public const FILES = [
        'es_ES.aff' => 'e73a9bf8e1383f4986a5dc9e2fbed49371c0c61f511c626d15586bd433c1cad9',
        'es_ES.dic' => '6975dddec3d5d2c676069537bc67b4b5f786c65c5d4cf6703a82acf779ac9ec1',
    ];

    /**
     * Suffix flags that only attach pronouns to verbs ("dámelo", "búscale"). The game, like the
     * RAE dictionary, does not accept enclitic forms. Derived from v2.9: every rule of these flags
     * adds a pronoun ending. N (-illa), T (-ble) and 🧠 (-mente) look similar but are real words.
     */
    private const ENCLITIC_FLAGS = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäñòóôõöøùú';

    /** Verb flags that mix regular forms with a few enclitic ones ("comerse", "yéndose"). */
    private const MIXED_VERB_FLAGS = 'DEIRX';

    private const ENCLITIC_ENDING = '/(ndo|[aeiáéí]r)(me|te|se|nos|os|lo|la|le|los|las|les)+$/u';

    public static function url(string $file): string
    {
        return self::BASE_URL.$file;
    }

    public static function isEnclitic(string $flag, string $form): bool
    {
        if (str_contains(self::ENCLITIC_FLAGS, $flag)) {
            return true;
        }

        return str_contains(self::MIXED_VERB_FLAGS, $flag) && preg_match(self::ENCLITIC_ENDING, $form) === 1;
    }

    /** Lowercase letters only: rules out proper nouns, acronyms and abbreviations ("Abel", "ADN", "Dr"). */
    public static function isLowercaseWord(string $word): bool
    {
        return preg_match('/^\p{Ll}+$/u', $word) === 1;
    }

    /** Game words: lowercase letters only and at least 3 of them. */
    public static function isPlayable(string $form): bool
    {
        return mb_strlen($form) >= 3 && self::isLowercaseWord($form);
    }
}
