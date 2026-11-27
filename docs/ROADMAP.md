# Roadmap

Update this file at the end of every task: tick what is done, add findings, move new ideas to the backlog.

## Current phase: F0 – Foundation

## F0 – Foundation
- [x] Project `CLAUDE.md` and `.claude/rules/` (game, dictionary, realtime, design)
- [x] `git init` + remote `origin` (https://github.com/Rob-Gon/matricular)
- [x] Initial commit and push
- [x] Fix local PHP PATH so `php` is Laragon 8.3 (user action)
- [ ] Install Claude Code plugins (user action, commands in the Obsidian note "Plugins Claude Code - Instalación")
- [ ] Configure GitHub repository settings (user action, see `docs/github-setup.md`)
- [ ] Larastan + Pint config, GitHub Actions CI (Pint, Larastan, tests, `npm run build`)
- [x] Real README (install, run, test)
- [ ] Wipe local test data (`migrate:fresh`, local only; start MySQL in Laragon first)

## F1 – Fixes & hardening (each fix with a test)
- [ ] Run ownership: Policy so a user can only touch their own runs (`Run::find` without owner check today)
- [ ] Plate generated and stored server-side; stop trusting `char1..3` from the client
- [ ] Server-side timer: `started_at`/`ends_at` on runs, reject late words
- [ ] `beforeunload` deletes every run (also on "play again"/"menu"); `resetRun` creates a run with `mode = null`
- [ ] Multibyte + accent-insensitive word handling (`strtoupper`/`count_chars` break Ñ and accents; API rejects "camion")
- [ ] Duplicate words in the same run must not score
- [ ] XSS: `innerHTML` with user names in `scores-manager.js`; `.html()` with server messages in `game-manager.js`
- [ ] Rankings expose user emails; decide whether soft-deleted runs count
- [ ] `Http` call: remove `verify => false`, add timeout, handle failure without losing points
- [ ] Form Requests for auth/profile/game; rate limiting on login, register and word submission
- [ ] Delete account requires password confirmation
- [ ] Remove dead `app/Http/Kernel.php`; register or drop `CheckSessionExpiration`; align JS session warning with `SESSION_LIFETIME`
- [ ] Broken route `game.infinite` → missing `infiniteView`
- [ ] Achievements: seeder column mismatch (`name` vs `achievement`), typo `$achievemen`, duplicated entries, wrong model relations, missing `$fillable`
- [ ] Game start via GET + redirect (POST + reload asks to resubmit the form)

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
