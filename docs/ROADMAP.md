# Roadmap

Update this file at the end of every task: tick what is done, add findings, move new ideas to the backlog.

## Current phase: F1 – Fixes & hardening (PR 1–2 of 4 done)

## F0 – Foundation
- [x] Project `CLAUDE.md` and `.claude/rules/` (game, dictionary, realtime, design)
- [x] `git init` + remote `origin` (https://github.com/Rob-Gon/matricular)
- [x] Initial commit and push
- [x] Fix local PHP PATH so `php` is Laragon 8.3 (user action)
- [x] Install Claude Code plugins (user action, commands in the Obsidian note "Plugins Claude Code - Instalación")
- [ ] Configure GitHub repository settings (user action, see `docs/github-setup.md` or the Obsidian note "Guía Settings GitHub")
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
- [ ] Form Requests for login/register/profile; rate limiting on login and register
- [ ] Delete account requires the current password
- [ ] XSS: `innerHTML` with user names in `scores-manager.js`
- [ ] Remove user emails from rankings (view and JSON)
- [ ] Remove dead `app/Http/Kernel.php`, `CheckSessionExpiration`, `session-manager.js`, `/extend-session` and the `session-start-time` meta (decision: rely on Laravel session + remember me)

### PR 4 · `fix/achievements-models`
- [ ] Seeder column mismatch (`name` vs `achievement`), typo `$achievemen`, duplicated entries, "!" instead of "¡"
- [ ] Model relations (`belongsToMany`) and `$fillable`
- [ ] `phpstan-baseline.neon` down to zero

## F2 – Local dictionary
- [ ] Verify licenses (Wiktionary/kaikki CC BY-SA, Hunspell es_ES)
- [ ] `words` table + import command (form, key, lemma, pos, length, source)
- [ ] `WordValidator` service: local lookup → rae-api fallback with cache
- [ ] `plates` table with solution counts; generator only picks solvable plates
- [ ] Benchmark validation latency (p95)

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

## F5 – Achievements
- [ ] Event-driven unlocks (`WordAccepted`, `RunFinished`), achievements page

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
- [ ] Security headers (CSP, HSTS), error monitoring, backups, uptime on `/up`
- [ ] Load test lobbies at 16 players

## Backlog (new ideas)
- (empty)
