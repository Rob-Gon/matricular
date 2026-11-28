# Matricular

<!-- This file overrides the global rules in ~/.claude/. -->

## Project
Personal project. A competitive word game in the browser: the player gets the letters of a Spanish license plate (3 consonants, no vowels, no Ñ, no Q) and must form valid Spanish words containing them, as many and as fast as possible.

- Solo modes: countdown (fixed durations) and infinite.
- Multiplayer (planned): lobbies joined by code + optional password, 2–16 players, three competitive formats (see `.claude/rules/game.md`).
- Registered users only. No guest accounts.
- Current state and next steps: `docs/ROADMAP.md`. Read it at the start of every session.
- Personal docs, open security findings and incidents live in the owner's Obsidian folder, linked from the uncommitted `CLAUDE.local.md`. Never document unfixed vulnerabilities in this public repository.

## Stack & versions
- PHP 8.3 (Laragon) · Laravel 12 · MySQL 8.4 · Node 26
- Frontend target: Blade + Alpine.js + Tailwind CSS 4, bundled with Vite.
- The legacy frontend (Bootstrap, jQuery, Sass, Font Awesome, SweetAlert2) was removed in F3. Do not bring it back.
- Realtime (planned): Laravel Reverb + laravel-echo. Redis for cache, queues and locks in production.
- Tests: PHPUnit (already in use; do not switch to Pest).

## Local environment
- URL: http://matricular.test
- Database: `matricular` (MySQL, root, 127.0.0.1:3306). Local data is disposable test data.
- PHP must be Laragon's 8.3 (`D:\laragon\bin\php\php-8.3.37-Win32-vs16-x64`). If `where php` resolves `C:\php` first, stop and warn the user: that build lacks `mbstring` and tests fail.

## Commands
- install: `composer install && npm install`
- dev: `npm run dev` (served by Laragon, never `php artisan serve`); multiplayer also needs `php artisan reverb:start` (WebSockets on localhost:8080)
- test: `php artisan test`
- lint: `vendor/bin/pint`
- build: `npm run build`
- dictionary: `php artisan dictionary:import` (downloads the pinned RLA-ES Hunspell files to `storage/app/dictionary/`, ~2 min), then `php artisan dictionary:categories` (Spanish Wiktionary via kaikki.org, ~100 MB, ~4 min) and `php artisan dictionary:plates` (solution counts and shortest example words per plate, ~35 s; also scheduled weekly)

## Architecture
- `app/Http/Controllers` – thin controllers (currently fat; being refactored).
- `app/Enums` – game modes, letter sets, countdown durations (runs store `letter_set` + `ordered`).
- `app/Models` – `User`, `Run`, `RunWord` (every attempt in a run), `Achievement` (pivot table `user_achievements`), `Lobby` + `LobbyPlayer` (multiplayer rooms).
- `app/Services/LobbyManager` – every lobby change (join, leave and host succession, kick, ready, colour, rules) runs with the lobby row locked and broadcasts `LobbyUpdated` (full state) on the presence channel `lobby.{id}`; `resources/js/lobby.js` renders and animates the grid.
- `app/Services/RaceManager` + `LobbyMatch` – parallel race and shared rounds (same flow; `LobbyMatch::isRounds()` keeps the plate per round and `SubmitWord` scores a word only for whoever said it first in the round), with podium tiebreaks (`MatchTiebreak`). Parallel race: the host starts it, every player gets a run (`runs.lobby_match_id`) on the match's shared plate sequence, scores are broadcast as `RaceScored`, and the match closes when the clock runs out. Run's `solo` global scope hides multiplayer runs from every solo query; use `withoutGlobalScope(Run::SOLO_SCOPE)` (or `LobbyMatch::runs()`) to reach them. The race reuses `main/game.blade.php` with `resources/js/race.js` (traffic light, live scoreboard).
- `app/Services/FirstWins` – "first wins": the shared plate's state lives on `lobby_matches` (`plate_number`, `plate_started_at`, `plate_closes_at`) and changes only under the match lock; words are judged outside it and ordered by arrival, `runs.answering_since` holds the next plate for a word still being judged, and `FirstPlateUpdated` (`race.plate`) tells clients to call `race/sync`.
- `app/Achievements` + `config/achievements.php` – achievement conditions and catalogue; `AchievementUnlocker` evaluates them. After editing the catalogue run `php artisan db:seed --class=AchievementsSeeder`.
- `resources/js/game.js` – solo game client (Alpine component `game`, registered in `site.js`); the server stays authoritative.
- `resources/js/sound.js` + `resources/sounds/{sfx,music}` – Howler.js player (Alpine store `sound`). Effects and tracks are picked up by file name; credit every new asset on `/creditos`.
- `resources/views/components` – design system as anonymous Blade components (`plate`, `car`, `odometer`, `fuel-gauge`, `road-scene`, `sign`, `sticker`, `field`, `option`, `flash`). Every page uses `<x-layouts.app>`.
- `resources/css/app.css` – Tailwind 4 theme tokens and component classes (`btn`, `paper`, `title-ink`, `field-*`, `opt`, `chip`, `plate`, `car`, `odometer`).
- Target layout for new code: `app/Actions` or `app/Services` for game logic (word validation, plate generation, scoring), Form Requests for input, Policies for ownership, Events for achievements/broadcasts.

## Project conventions (override global rules)
- UI copy is in Spanish. Code, comments, commits and technical docs in English.
- The server is authoritative for everything that affects score: current plate, timers, scoring, word validity. The client only renders.
- Word input is case-, accent- and diacritic-insensitive for the player (Ñ stays a distinct letter). See `.claude/rules/dictionary.md`.
- No mascot/illustrated character. Visual identity is built from vehicle/road SVG elements (see `.claude/rules/design.md`).

## Deploy
- Laravel Forge + one Hetzner Cloud VPS (EU) running Nginx, PHP-FPM, MySQL, Redis, Reverb, queue worker and scheduler. The VPS may host other personal projects.
- Branch deployed: `main`. Environments: local · production (URL pending).

## External services & integrations
- Word validation: `App\Services\Dictionary` checks the local `words` table; the unofficial `https://rae-api.com/api/words/{word}` is only a cached fallback, and words it confirms are learned (`source = rae-api`).
- Local dictionary: `words` table built from RLA-ES v2.9 es_ES (GPL/LGPL/MPL tri-license, pinned in `app/Dictionary/RlaEsSource.php`). Parts of speech in `word_categories` (written word → pos; a word's category is its form's or its lemma's) from Spanish Wiktionary via kaikki.org (CC BY-SA, credited on `/creditos`).
- GitHub: https://github.com/Rob-Gon/matricular (public). Never commit secrets or real user data.

## Recommended Claude Code plugins
From `claude-plugins-official`: laravel-boost, frontend-design, security-guidance, playwright, context7, feature-dev, code-review, pr-review-toolkit, claude-md-management. Optional (need npm global installs): php-lsp, typescript-lsp.

## Do not touch
- `.env` (ask first).
- Never bulk-download the RAE dictionary or the rae-api.com service.
