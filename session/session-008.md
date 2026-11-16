# Session 008

Date: 2026-11-16 (CET). The last 45 minutes: a reduced rep page (3.1, 3.3, 3.4), then the cut list, the time log and the commits.

Conventions: as in session 001. Replies are condensed.

---

## 1. Owner

45 minutes are left: what can we do to show something?

**Assistant:** Read `PLAN.md`, `NOTES.md` and the time log. Proposed: the change request for Greg (5.7), the holdout read-out (6.2) and a small demo, with the last 15 minutes kept for the README and the hand-in. Listed what to cut and what the "never cut" list could not fully cover (quote verification, the full money code).

## 2. Owner

Is the front end not in phase 3? The app has to open from a phone, so the front end is the logical priority.

**Assistant:** Agreed: the page comes first. New order: 3.1 reduced (CSV adapter only), 3.3, 3.4, then 5.7; 6.2 goes to the cut list. Noted that a page reachable beyond localhost needs the token check (3.6).

## 3. Owner

3.1.

**Assistant:** The `developer` agent wrote `SandboxClientInterface`, `CsvSandboxClient`, `SoldOutCase`/`SoldOutCases`, a shared `DataPackCsv` and tests; the `reviewer` agent found no blocker, only nits (a lazy merchants check, lexicographic order, timezone of the input date). `make lint` green at 179 tests.

## 4. Owner

There is no controller; how can I check it?

**Assistant:** Right: 3.1 is only the data layer. Gave the one-line test command and proposed 3.3 and 3.4 together.

## 5. Owner

Commit and 3.3 + 3.4.

**Assistant:** Committed 3.1 (`612b55f`). The `developer` agent wrote `SoldOutService` (net value of a call per size band minus rep time, 12 minutes at £20 an hour as named parameters; do-not-contact first; calls ranked by net), the estimates reader and the wiring, 213 tests. The main session wrote the controller and the Twig page (Bootstrap 5.3.3 from a CDN with a verified SRI hash) and installed `symfony/twig-bundle`. The page showed 108 sold-outs for 27 October 2026. The `reviewer` agent found no blocker; fixed: an impossible date now gives 400, placeholder zeros are no longer shown for do-not-contact or missing estimates, and the header says calls first.

## 6. Owner

Write all the gaps into the README, log the time, add this session, commit and push.

**Assistant:** README: a cut list and limits section (no controller test, no token check, no detail view, no refund risk, estimates per band, no HTTP adapter, two-valued recommendation) and a list of what is not done. Time table: 25 minutes for 3.1, 3.3 and 3.4 (the assistant's estimate), total 164 of 180, 16 remaining. `PLAN.md` updated. This log written. Committed and pushed.
