# Session 004

Date: 2026-11-16 (CET). Phase 1, skeleton, tasks 1.1–1.6. About 26 minutes of active time (start estimated from the last commit, a 2-minute break by the owner's estimate).

Conventions: as in session 001. Assistant replies are given in full but condensed in wording.

---

## 1. Owner

Start phase 1, task 1.

**Assistant:** Task 1.1: `.gitignore` no longer ignores `.env`, and ignores `.env.local`, `.env.*.local`, `var/`, `vendor/`. Checked with `git check-ignore`. Committed on request (`747883f`).

## 2. Owner

Go (task 1.2).

**Assistant:** Added `.docker/php/{Dockerfile,Caddyfile,php.ini}`, `compose.yaml` and `compose.override.yaml`: FrankenPHP `1-php8.5` running as the host UID, Caddy on port 8080, PostgreSQL 18 with a healthcheck, the project mounted in the override. The image builds; PHP 8.5.12, `intl`, `opcache`, `pdo_pgsql`, `zip` and Composer are present. The Docker daemon was down and was started. The Caddyfile could not be validated yet because `public/` did not exist. Committed (`7ad0edf`).

## 3. Owner

Go (task 1.3).

**Assistant:** Added the `Makefile` (`init`, `upd`, `updb`, `down`, `in`, `cs`, `fix`, `stan`, `test`, `lint`). `.env.local` is created with the host UID when missing and is read by compose through `--env-file`. Checked with `make -n` and `docker compose config`. Committed (`26c3cb9`).

## 4. Owner

Go (task 1.4).

**Assistant:** Created the Symfony 7.4.23 skeleton inside a container, with `extra.symfony.docker` off so no recipe overwrote the compose files, and copied it into the repository without overwriting existing files. Added `runtime/frankenphp-symfony`. Removed recipe comments and the unused `cache.yaml`. Set `num 2` for the worker (the default started 16). The Caddyfile is copied into the image, so changing it needs `make updb`. Worker mode confirmed in the log; the root answers 404 because there is no route yet. Committed (`21886af`).

## 5. Owner

Go (task 1.5).

**Assistant:** Added PHPUnit 13, PHPStan 2 (level max, strict rules, Symfony extension) and PHP-CS-Fixer (`@Symfony`, risky rules, strict types, final classes). Minimum PHP raised to 8.5. The fixer added `declare(strict_types=1)` to the skeleton files and made `Kernel` final; PHPStan found two errors in `tests/bootstrap.php`, which was simplified. One smoke test boots the kernel in the `test` environment. `make lint` is green. Committed (`e5c3347`).

## 6. Owner

Go; a 404 at the root is fine for now (task 1.6).

**Assistant:** Cloned the repository to a clean directory and ran `make init`. Two defects surfaced. First, the worker started before `composer install` and the container died: `init` now builds, installs in a one-off container, then starts the stack. Second, `admin off` in the Caddyfile broke the base image's healthcheck and `--wait` failed: the line was removed. After the fix both containers are healthy, the root answers 404, and `make lint` is green. The test stack was removed with its volume.

## 7. Owner

The healthcheck problem should be recorded in the README as a known problem; there is no time to solve it properly.

**Assistant:** Added a "Known issues" section to `README.md`: the Caddy admin endpoint stays enabled inside the container (not published) because the base image's healthcheck reads it; a custom `healthcheck` in `compose.yaml` is the proper fix and was left out for lack of time.

## 8. Owner

Write the session to `session/` and the time, taking a couple of minutes off for a break.

**Assistant:** Added this file and a phase 1 row to the README time log: 26 minutes (13:15–13:43 minus 2), 79 of 180 used, 101 remaining. Phase 1 took 26 of 15 planned minutes; phases 2–7 plan 120 against 101 remaining, so 19 minutes must be cut by the cut order in `NOTES.md`. `PLAN.md` and `NOTES.md` carry the new figures. Task 1.6 and these changes are not committed yet.
