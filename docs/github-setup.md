# GitHub repository setup

Repository: https://github.com/Rob-Gon/matricular (public, GitHub Free). All paths below are under **Settings** of the repository.

## Plan notes (public repo on GitHub Free)
- Branch protection rules and rulesets are enforced on public repositories with GitHub Free.
- Secret scanning, push protection and CodeQL code scanning (default setup) are free for public repositories: turn them on.
- GitHub Actions minutes are free for public repositories on standard runners.
- Everything committed is public: never commit `.env`, dumps, credentials or real user data.

## General
- Default branch: `main`.
- Pull Requests: allow **squash merging** only; enable "Always suggest updating pull request branches"; enable **Automatically delete head branches**.
- Disable Wiki and Projects if unused.

## Branch protection for `main` (Rules → Rulesets)
- Target: default branch.
- Require a pull request before merging (0 approvals is fine for a solo project).
- Require status checks to pass: `checks` (workflow `CI`: Pint, Larastan, tests, build). Require branches to be up to date.
- Block force pushes. Restrict deletions.
- Require linear history.

## Security (Security / Code security)
- Dependency graph: on.
- Dependabot alerts: on. Dependabot security updates: on.
- Secret scanning: on. Push protection: on.
- Code scanning: CodeQL default setup (languages: JavaScript/TypeScript, Actions).
- Optional `.github/dependabot.yml` for weekly Composer, npm and GitHub Actions version updates (grouped, to avoid PR noise).
- Private vulnerability reporting: on.

## Actions
- Actions permissions: allow GitHub-authored actions plus an allow-list with `shivammathur/setup-php@*` (used by CI, not a verified creator).
- Workflow permissions: **Read repository contents** (least privilege); grant extra permissions per job in the workflow file.
- Do not allow Actions to create or approve pull requests.

## Secrets and environments (Secrets and variables → Actions)
- Environment `production`, restricted to branch `main`.
- Secret `FORGE_DEPLOY_HOOK_URL` in that environment: the Forge deploy hook that CI calls after all checks pass.
- Never store `.env` values here unless a workflow needs them; production env vars live in Forge.

## Local clone
- `git config core.autocrlf false` is not needed: `.gitattributes` forces LF.
- Branch naming: `feature/…`, `fix/…`, `chore/…`. Conventional Commits. No attribution trailers.
