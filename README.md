# Matricular

A browser word game built on Spanish license plates: you get three consonants and must find valid Spanish words that contain them, as many and as fast as possible. Solo modes (countdown and infinite) today; multiplayer lobbies for up to 16 players are planned.

Project status and plan: [`docs/ROADMAP.md`](docs/ROADMAP.md).

## Requirements
- PHP 8.2+ with `mbstring` and `intl` (Laragon PHP 8.3 locally)
- Composer, Node 20+ and npm
- MySQL 8

## Local setup (Laragon)
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set in `.env`:
```
APP_URL=http://matricular.test
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=matricular
DB_USERNAME=root
DB_PASSWORD=
```

Then:
```bash
php artisan migrate --seed
php artisan dictionary:import       # ~600k Spanish word forms, about 2 minutes
php artisan dictionary:categories   # parts of speech from Spanish Wiktionary, ~100 MB download, about 4 minutes
php artisan dictionary:plates       # solution counts for every plate, about 15 seconds
npm run dev
```

The site is served by Laragon at http://matricular.test (no `php artisan serve`).

## Tests and linting
```bash
php artisan test
vendor/bin/pint
```

## Repository
- Conventional Commits, one branch per change (`feature/…`, `fix/…`, `chore/…`), merged to `main` through pull requests.
