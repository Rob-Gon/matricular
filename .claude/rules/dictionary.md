# Dictionary & word validation

## Normalization (player input is forgiving)
- Case-insensitive, accents and diaeresis optional: `camion`, `CAMIÓN` and `Camión` all match `camión`.
- Ñ is its own letter: `nino` must not match `niño`.
- Use multibyte-safe functions only (`mb_*`, `Str::`, `Normalizer` from intl). Never `strtoupper`, `strlen` or `count_chars` on words.
- Store a normalized lookup key per word: uppercase, diacritics removed except Ñ. Look up by key.

## Sources
- Local `words` table imported by an artisan command from open sources (Wiktionary extracts via kaikki.org, Hunspell es_ES). Keep `form` (with accents), `key`, `lemma`, `pos` (part of speech), `length`, `source`.
- Show the required attribution (CC BY-SA) on the site.
- `rae-api.com` is an unofficial service: only as an on-miss fallback, with a short timeout, SSL verification on, and cached results. Never bulk-download it.

## Performance
- Validation must be one indexed lookup (target p95 < 5 ms inside the app, excluding network).
- Precomputed `plates` table: solution counts per plate and setting, used by the plate generator and for "words you could have made" after a game.
