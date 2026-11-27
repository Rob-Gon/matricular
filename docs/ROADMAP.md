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
- [x] Visual direction approved by the owner: 1930s rubber-hose cartoon (original art), palette Ink #000000 · Celluloid #E7D7B0 · Enamel #FBF8EF · Stage green #265E57 · Mustard #E9A92B · Signal red #C8361F · Plate blue #1F3A97, fonts Chango + Big Shoulders (design canvas kept in the owner's Obsidian)
Done 2026-11-27 in five PRs plus follow-ups (skip key, desktop road in perspective, time zones). Every page uses `components/layouts/app`.

### PR 1 · `feat/frontend-foundation`
- [x] Tailwind 4 theme (palette, Chango + Big Shoulders self-hosted latin woff2, preloaded), Alpine (`resources/js/site.js`)
- [x] Blade components: `plate`, `car`, `odometer`, `sign`, `field`, `flash`, `layouts.app` (header with mobile menu, footer, skip link, grain + vignette, shared ink-boil filters)
- [x] Home, "how to play" guide (`/como-se-juega`), credits (fonts credited), login, register, profile (delete account in a `<dialog>` with its own error bag)
- [x] SEO basics on public pages: unique title and description, canonical, Open Graph, one h1, `/sitemap.xml`; profile is `noindex`
- [x] Favicon (`favicon.svg` + `favicon.ico`)

### PR 2 · `feat/game-settings`
- [x] Settings replace Easy/Medium/Hard: letter set (simple | full) and ordered letters, combinable (simple + ordered is new); `runs.difficulty` migrated to `letter_set` + `ordered` (reversible, backfilled); `PlateGenerator` cached per combination; "Matricúlame esta" now needs all letters in order
- [x] "Prepara el viaje" settings screen (`/game/setup`) with a live summary; the legacy menu links to it and its AJAX modals are gone
- [x] Rankings filter accepts `letter_set` and `ordered` (validated); the legacy scores page lost its difficulty icons until PR 4 rebuilds it

### PR 3 · `feat/game-screen`
- [x] Game screen in Alpine + `fetch` (`resources/js/game.js`) replacing `game-manager.js`: car + plate drop, fuel-gauge timer counted against a deadline, odometer, road-sign feedback, achievement toast, native `<dialog>` to end an infinite run
- [x] Skip button renamed "Pasamatrícula" (−5)
- [x] Keyboard shortcuts: Enter submits the word (native form submit); Control (Command on Mac) pressed and released alone triggers "Pasamatrícula" (owner's choice, replaced Shift; shortcuts such as Ctrl+Z do not count). Keys shown on the buttons (hidden on small screens) and in the guide
- [x] "Fin del trayecto" end screen (`/game/{run}/summary`, "no entry" sign) with the trip receipt: correct/failed words, skips, longest word, personal best and the run's achievements. Opening it closes a countdown that ran out with the tab closed, so its run achievements are no longer skipped
- [x] "Sugerir «palabra»" stays hidden until the suggestions flow exists
- [x] Desktop (≥1280 px): the car drives on a road in perspective (`x-road-scene`: sky, low sun, hills, road to the horizon) and the word form is a card standing on the road; smaller screens keep the flat road band (owner's choice from the canvas test)

### PR 4 · `feat/legacy-removal`
- [x] Menu (personal best, two mode cards), scores and achievements pages on the new layout (fixes the continuously animated menu buttons that Playwright cannot click)
- [x] Rankings filtered with a plain GET form (mode, letters, order, duration); invalid query values are ignored. The AJAX `scores.filter` endpoint is gone; recent runs link to their trip receipt
- [x] Removed Bootstrap, jQuery, Font Awesome, Sass, SweetAlert2, axios, `master-layout` and the legacy JS; Vite builds only `app.css` and `site.js`
- [x] Accessibility pass: current page marked with `aria-current` in the nav, no horizontal scroll at 390 px on any page (a screen-reader-only label inside a scrolling table escaped its box), keyboard play and reduced motion already covered by PR 3

### PR 5 · `feat/sound`
- [x] Sound player with Howler.js (`resources/js/sound.js`, Alpine store `sound`): effects for correct, wrong, warning, plate skip, achievement and the last ten seconds; effects, music and volume persisted in `localStorage`; music starts on the first key press or click (browser autoplay rules) and fades out when the run ends
- [x] Effects: Kenney "Interface Sounds" (CC0). Music: Tom Feldmann's "Old Cartoon Music Pack Lite" (CC BY 4.0, 6 tracks converted to 112 kbps MP3 with normalized loudness), shuffled as a playlist; both credited on `/creditos`. Any file dropped in `resources/sounds/music` joins the playlist

## F4 – Game data & stats
### PR 1 · `feat/run-words`
- [x] `run_words`: one row per attempt (word or skip) with plate, input as typed, normalized key, dictionary spelling (`form`, not a foreign key because `dictionary:import` rebuilds `words`), valid, reason (`missing_letters`, `wrong_order`, `not_in_dictionary`, `repeated`), points and `elapsed_ms` since the plate was dealt (`runs.plate_dealt_at`, milliseconds)
- [x] Backfill from the JSON arrays (old runs have no plates, times or reasons; accents recovered from `words`)
- [x] Readers moved to `run_words`: repeated-word check, `attempted` scope, rankings, first-game achievement, trip receipt (accents, "la más rápida")
- [x] Repeated words are now recorded (0 points)

### PR 2 · `refactor/drop-run-json`
- [x] Stop writing and drop `runs.words_correct`, `words_error`, `chars_skipped` (separate deploy, so the previous code keeps working during PR 1's rollout; rollback rebuilds them from `run_words`); `RunFactory::attempts()` state for tests

### PR 3 · `feat/trip-stats`
- [ ] End-of-game stats: rejection reasons, accuracy, average answer time, "words you could have made" per plate (`plates` + `words`)

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
- [ ] `Sitemap:` line in `public/robots.txt` once the domain is known; Open Graph image
- [ ] Deploy script runs `php artisan db:seed --class=AchievementsSeeder --force` after migrations
- [ ] Run `php artisan dictionary:import`, `dictionary:categories` and `dictionary:plates` once on the server (and after updating the pinned dictionary version)
- [ ] Security headers (CSP, HSTS), error monitoring, backups, uptime on `/up`
- [ ] Load test lobbies at 16 players

## Backlog (new ideas)
- Difficulty by solution count (e.g. hard deals plates with few solutions) and "words you could have made" at the end of a game, both from the `plates`/`words` tables
- Performance: enable OPcache in Laragon (user action) and re-measure `check-word`; Redis for cache, session and rate limiter in production (F7); endpoint p95 in the F7 load test
- Word suggestions: "Sugerir «palabra»" button after a dictionary rejection → `word_suggestions` table → automatic checks (RAE API, Wiktionary) → anything not confirmed is emailed to the owner with approve/reject links (signed URLs, or n8n calling a signed webhook) that update Laravel. The owner never edits the database by hand
