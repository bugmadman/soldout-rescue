# Session 009

Date: 2026-11-16 (CET). Task 3.6: Basic auth on the whole app, then the notes, the time log and the commit.

Conventions: as in session 001. Replies are condensed.

---

## 1. Owner

Close the page with basic auth. The login and password must be in `.env.local`.

**Assistant:** Rated it `[C]` (access to the tool). The `developer` agent wrote `BasicAuthSubscriber` (a `kernel.request` listener at priority 256, so unknown paths are challenged too; `hash_equals` on both fields; 401 with `WWW-Authenticate`; a `\RuntimeException` when the variables are empty, so it fails closed), the wiring in `services.yaml`, empty defaults in `.env`, test values in `.env.test`, a generated login and password appended to `.env.local`, and unit and wiring tests. `make lint` green at 232 tests; checked on the running stack: 401 without credentials, 401 with a wrong password, 200 with the right ones. The `reviewer` agent found no blocker. Fixed: removed a test that only restated the priority constant (231 tests). Open points: the work was not in PLAN.md; the container serves plain HTTP, so the password is readable on the wire; static files under `public/` would bypass the check (none today).

## 2. Owner

Record the session, the time and commit.

**Assistant:** 3.6 marked done in `PLAN.md` (as Basic auth instead of the shared token), `NOTES.md` updated with the decision and the rejected token, the README limit rewritten (Basic auth over plain HTTP), time table: 8 minutes (the assistant's estimate), total 172 of 180, 8 remaining. This log written. Committed with `Task: 3.6`, not pushed.
