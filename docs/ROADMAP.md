# Roadmap

Update this file at the end of every task: tick what is done, add findings, move new ideas to the backlog.

## Current phase: F3 – Frontend rebuild (F2 done)

## F0 – Foundation
- [x] Project `CLAUDE.md` and `.claude/rules/` (game, dictionary, realtime, design)
- [x] `git init` + remote `origin` (https://github.com/Rob-Gon/matricular)
- [x] Initial commit and push
- [x] Fix local PHP PATH so `php` is Laragon 8.3 (user action)
- [x] Install Claude Code plugins (user action, commands in the Obsidian note "Plugins Claude Code - Instalación")
- [x] Configure GitHub repository settings (user action; personal checklist kept in Obsidian)
- [x] Larastan (level 5 + baseline of 11 legacy errors) + Pint, GitHub Actions CI (Pint, Larastan, tests, `npm run build`)
- [x] Update vulnerable dependencies (Laravel 12.18 → 12.74; `composer audit` and `npm audit` clean) and run both audits in CI
- [x] Laravel Boost (`laravel/boost` dev dependency; the plugin provides the MCP config, `boost:install` not needed)
- [x] Real README (install, run, test)
- [x] Wipe local test data (`migrate:fresh`, local only)

## F1 – Fixes & hardening (each fix with a test)
Split in four PRs: game hardening → word normalization → auth hardening → achievements models.

### PR 1 · `fix/game-hardening`
- [x] Run ownership: `RunPolicy` (`play`), every game endpoint authorizes
- [x] Plate generated and stored server-side (`runs.plate`, `PlateGenerator`); `char1..3` from the client ignored
- [x] Server-side timer: `runs.ends_at` + `ended_at`, words after `ends_at` + 2 s grace rejected with 409
- [x] Removed `beforeunload`/`resetRun` (deleted every run); game start is POST + redirect to `GET /game/{run}`; "play again" is a form
- [x] Duplicate words in the same run do not score
- [x] `.html()` with server messages in `game-manager.js` replaced by text nodes
- [x] Rankings and recent runs count only runs with at least one attempted word (soft delete no longer used)
- [x] Dictionary call moved to `RaeDictionary`: SSL verification on, timeouts, outage does not penalize
- [x] `StartGameRequest`/`SubmitWordRequest`, `throttle:game` (120/min per user) on game actions
- [x] Broken route `game.infinite` removed
- [x] `runs.user_id` foreign key cascades, so deleting an account with runs no longer fails

### PR 2 · `fix/word-normalization`
- [x] Accent/case-insensitive input with Ñ kept (`WordNormalizer`, intl `Normalizer`); words stored and de-duplicated by key
- [x] API needs accents ("camion" → NOT_FOUND with suggestion "camión"): accept when a suggestion normalizes to the same key
- [x] Cache dictionary answers (30 days, outages not cached)
- [x] Words must be letters only (`\p{L}+`), rejected before scoring

### PR 3 · `fix/auth-hardening`
- [x] Form Requests for login/register/profile; login locks out after 5 failures per email+IP, register throttled 5/min
- [x] Delete account requires the current password and logs the user out
- [x] XSS: `innerHTML` with user names in `scores-manager.js` (now text nodes)
- [x] Remove user emails from rankings (view and JSON); `remember_token` no longer mass assignable
- [x] Remove dead `app/Http/Kernel.php`, `CheckSessionExpiration`, `session-manager.js`, `/extend-session` and the `session-start-time` meta (decision: rely on Laravel session + remember me)

### PR 4 · `feat/achievements` (replaced the planned models fix; brings F5 forward)
- [x] Catalogue in `config/achievements.php` (stable `key`, name, description, icon, hidden); `AchievementsSeeder` upserts it by key
- [x] One class per unlock condition in `app/Achievements` (`WordAchievement` after each accepted word, `RunAchievement` when a run ends)
- [x] `AchievementUnlocker`: one query for what the player owns, in-memory checks, race-safe `insertOrIgnore`, platinum when all others are owned
- [x] Toast (Bootstrap toast for now, no focus steal) from the `achievements` field of game responses; `/achievements` page with hidden ones as "???"
- [x] Initial set: first game, long word (12+), palindrome (hidden), 500 points, 250 points on hard, 5 games in 10 minutes, 25 games in a day, platinum
- [x] `phpstan-baseline.neon` removed (zero legacy errors)

## F2 – Local dictionary
- [x] Licenses: RLA-ES is GPL 3+/LGPL 3+/MPL 1.1+ (choose one); Wiktionary content is CC BY-SA (kaikki.org publishes no separate license)
- [x] PR 1 `feat/dictionary-import`: `words` table (key, form, lemma, pos, source) + `dictionary:import` with a PHP Hunspell expander
  - 612,645 forms from 56,901 lemmas in ~2 min; 100% of a 60-word sample of common forms (plurals, irregular verbs, accents, ñ); lookup p50 0.25 ms, p95 0.31 ms
  - Enclitic forms ("dámelo", "comerlo") excluded like the RAE; a few appear as literal RLA-ES entries ("dámelo", "yéndose"), accepted as-is
- [x] PR 2 `feat/dictionary-pos`: `word_categories` from Spanish Wiktionary (kaikki.org JSONL, streamed) + `/creditos` page with CC BY-SA attribution
  - 809,644 word/category rows in ~4 min; 95.7% of dictionary forms (72.9% of lemmas) get a category through their form or lemma
- [x] PR 3 `feat/local-word-validation`: `Dictionary` service checks `words` first (one indexed lookup), RAE API as cached fallback; confirmed words are learned (`source = rae-api`, word/lemma/categories only); test pins local lookups under 5 ms p95
  - Measured locally: a whole `check-word` request takes ~600 ms even when the word is rejected before any lookup. The cost is the environment, not the dictionary: no OPcache in Laragon (~250 ms for `/up`) and database-backed session, cache and rate limiter
- [x] PR 4 `feat/solvable-plates`: `plates` table (8,000 plates) with solution counts in any order and in plate order; `PlateGenerator` only deals plates with at least `MIN_SOLUTIONS` (10), cached per difficulty; random fallback without the table; rebuilt weekly by the scheduler
  - 1,420 plates (17.7%) had no solution at all and could be dealt before; eligible now: 2,685 easy, 5,634 medium, 4,203 hard

## F3 – Frontend rebuild
- [ ] Tailwind 4 + Alpine; remove Bootstrap, jQuery, Sass, CDNs (fonts/icons self-hosted)
- [ ] Vehicle-themed design system as Blade components (see `.claude/rules/design.md`)
- [ ] Game settings screen (letter set, ordered letters, mode/time) replacing Easy/Medium/Hard
- [ ] "How to play" guide page: rules, scoring, accents/case are optional, Ñ is its own letter, examples per setting
- [ ] Accessibility pass (keyboard play, focus, reduced motion) and SEO basics (titles, meta, OG, sitemap)
- [ ] Favicon (`/favicon.ico` returns 404 today)
- [ ] Menu buttons animate continuously (Playwright cannot click them: "element is not stable"); respect `prefers-reduced-motion`

## F4 – Game data & stats
- [ ] `run_words` table (word, plate, valid, reason, points, elapsed_ms) replacing JSON arrays
- [ ] Settings stored per run; end-of-game stats screen

## F5 – Achievements (foundation done in F1 · PR 4)
- [ ] Review and redefine the achievement list with the user
- [ ] Part-of-speech achievements after F2: 5 nouns / adjectives / verbs in a row ("Bachillerato de letras / artes / ciencias")
- [ ] Run achievements for countdowns that expire with the tab closed (today only checked when the client calls `game.end`)
- [ ] Optional Steam-like extras: progress (12/25), rarity (% of players)
- [ ] Toast moves to Alpine in F3; multiplayer unlocks pushed over Reverb in F6

## F6 – Multiplayer
- [ ] Reverb + Echo setup, lobby create/join (code + optional password), presence and ready state
- [ ] Formats: parallel race, shared plate rounds, first wins
- [ ] Tie handling: tie window for near-simultaneous answers, `tie_resolution` lobby option (shared | tiebreak plate)
- [ ] Synced start (traffic light), live scoreboard, end-of-game stats, reconnection
- [ ] Playwright E2E with multiple players

## F7 – Production
- [x] Hosting decision: Forge + one VPS (Hetzner, EU), shared with other personal projects; move to a dedicated server if load requires it
- [ ] Domain
- [ ] Redis, Reverb daemon, queue worker, scheduler, SSL
- [ ] Deploy script runs `php artisan db:seed --class=AchievementsSeeder --force` after migrations
- [ ] Run `php artisan dictionary:import`, `dictionary:categories` and `dictionary:plates` once on the server (and after updating the pinned dictionary version)
- [ ] Security headers (CSP, HSTS), error monitoring, backups, uptime on `/up`
- [ ] Load test lobbies at 16 players

## Backlog (new ideas)
- Difficulty by solution count (e.g. hard deals plates with few solutions) and "words you could have made" at the end of a game, both from the `plates`/`words` tables
- Performance: enable OPcache in Laragon (user action) and re-measure `check-word`; Redis for cache, session and rate limiter in production (F7); endpoint p95 in the F7 load test
- Word suggestions: "Sugerir palabra" button after a rejected word → `word_suggestions` table → automatic checks (RAE API, Wiktionary) → manual approval for anything not confirmed; n8n optional for notifications/approval via signed webhook
