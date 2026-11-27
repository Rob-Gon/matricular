# GitHub repository setup

Repository: https://github.com/Rob-Gon/matricular (private). All paths below are under **Settings** of the repository.

## Plan limits (GitHub Free, private repo)
- Branch protection rules and rulesets are **not enforced on private repositories with GitHub Free**. Options: GitHub Pro, make the repo public, or rely on discipline (never push to `main`, always PR) until then.
- Secret scanning push protection and CodeQL code scanning for private repos need paid GitHub security products. Dependabot alerts and security updates are free.
- GitHub Actions: free minutes per month for private repos are limited; keep CI fast (cache Composer and npm).

## General
- Default branch: `main`.
- Pull Requests: allow **squash merging** only; enable "Always suggest updating pull request branches"; enable **Automatically delete head branches**.
- Disable Wiki and Projects if unused.

## Branch protection for `main` (Rules → Rulesets, when the plan allows it)
- Target: default branch.
- Require a pull request before merging (0 approvals is fine for a solo project).
- Require status checks to pass: the CI job (Pint, Larastan, tests, build). Require branches to be up to date.
- Block force pushes. Restrict deletions.
- Require linear history.

## Security (Security / Code security)
- Dependency graph: on.
- Dependabot alerts: on. Dependabot security updates: on.
- Optional `.github/dependabot.yml` for weekly Composer, npm and GitHub Actions version updates (grouped, to avoid PR noise).
- Private vulnerability reporting: on if the repo becomes public.

## Actions
- Actions permissions: allow GitHub-authored actions and actions from verified creators (or a pinned allow-list).
- Workflow permissions: **Read repository contents** (least privilege); grant extra permissions per job in the workflow file.
- Do not allow Actions to create or approve pull requests.

## Secrets and environments (Secrets and variables → Actions)
- Environment `production`, restricted to branch `main`.
- Secret `FORGE_DEPLOY_HOOK_URL` in that environment: the Forge deploy hook that CI calls after all checks pass.
- Never store `.env` values here unless a workflow needs them; production env vars live in Forge.

## Local clone
- `git config core.autocrlf false` is not needed: `.gitattributes` forces LF.
- Branch naming: `feature/…`, `fix/…`, `chore/…`. Conventional Commits. No attribution trailers.
