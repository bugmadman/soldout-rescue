# Implementation plan

Source: [NOTES.md](NOTES.md). Tasks are done in phase order, within a phase — top to bottom. Mark `[x]` as they get done. `3.2` is the task's stable number; ` — reviewed` marks a task whose code the owner has reviewed (see WORKFLOW.md). `[S]`/`[C]` is the task's tier (simple/complex), see CLAUDE.md, "Model selection". Each phase has a minute budget; the time log is in README.md.

Budget: 150 minutes remaining at the start of this plan (180 total, 28 used). 145 planned, 5 spare.

## Architecture (brief)

- Symfony 7.4 / PHP 8.5 / PostgreSQL / FrankenPHP in Docker for the rep tool, the money model and the text pipeline. Python (standard library) for the routing change and the holdout read-out.
- Flow: sandbox API → money model + text findings → recommendation per sold-out → mobile page; a rep's mark goes back to the sandbox. Suppression list → routing service behind one switch. Holdout assignments + outcomes → read-out script.
- Deliberately not now: see "Rejected alternatives and cuts" in NOTES.md.

## Phase 0 — Process and plan (10 min)

- [x] [S] 0.1 `AGENTS.md`, `WORKFLOW.md`, `CLAUDE.md`, `.claude/agents/` adapted to this project
- [x] [S] 0.2 `NOTES.md` with the decisions so far
- [x] [S] 0.3 `PLAN.md` with minute budgets
- [ ] [S] 0.4 The owner agrees the plan; open questions in the NOTES inbox are answered

## Phase 1 — Skeleton (15 min)

- [ ] [S] 1.1 `.gitignore`: stop ignoring `.env`, ignore `.env.local`, `var/`, `vendor/`; `.env` holds defaults only, no key
- [ ] [S] 1.2 `.docker/php/` (Dockerfile, Caddyfile, php.ini) and `compose.yaml` + `compose.override.yaml` with PostgreSQL 18, FrankenPHP image
- [ ] [S] 1.3 `Makefile`: `init`, `upd`, `down`, `in`, `cs`, `fix`, `stan`, `test`, `lint`; `.env.local` created with the host UID
- [ ] [S] 1.4 Symfony 7.4 skeleton created inside the container, FrankenPHP worker mode on
- [ ] [S] 1.5 PHPUnit, PHPStan with strict rules, PHP-CS-Fixer (strict types, final, 120 columns by hand); one smoke test green
- [ ] [S] 1.6 Verify: `make init` from a clean state, the start page opens, `make lint` passes

## Phase 2 — Money model (20 min)

- [ ] [S] 2.1 Fixture CSVs (invented, tiny) for sold-outs, merchants, activities, schedules, merchant months
- [ ] [C] 2.2 `Money` VO in pence with explicit rounding, tested
- [ ] [C] 2.3 Historical estimates from the data pack (restock rate called vs. left alone by size band, GP after, refund rate), computed by a script, written to a small committed JSON of parameters, not the raw data
- [ ] [C] 2.4 `ChaseValue` calculation: GP of a cap raise minus rep time minus refund risk, as a low / central / high range, all parameters named, tested on edge cases
- [ ] [C] 2.5 Handling of the data traps: dedupe replayed activities, blank `rep_id`, blank 30-day GP, schedule coverage by `deal_id` and date
- [ ] [S] 2.6 Message to Priya: why the £11 is not used as the amount; ask whether she has auto-replenish and Deal Alert data and what £ rate per rep hour to use; her answer recorded in NOTES.md, never assumed

## Phase 3 — Rep tool (25 min)

- [ ] [C] 3.1 `SandboxClientInterface` and the HTTP adapter (key from `.env.local`, paging, 429 wait), with a fake for tests
- [ ] [C] 3.2 `Recommendation` enum and the rules: `call`, `dont_call`, `automation_covers`, `other`, each with a reason; do-not-contact always wins
- [ ] [S] 3.3 `SoldOutService`: today's sold-outs → recommendation + amount, ranked by amount
- [ ] [S] 3.4 Mobile Twig page with Bootstrap 5 from a CDN (pinned version, SRI): list with action, reason, £ range; a detail view with the evidence
- [ ] [S] 3.5 Mark form: `PUT /merchants/{id}/mark` with a code and a reason, `marked_by` from the access token's rep
- [ ] [C] 3.6 Access token check (shared secret from `.env.local`) on every page
- [ ] [S] 3.7 Verify on a phone-width viewport

## Phase 4 — Text analysis (25 min)

- [ ] [C] 4.1 Clean the text: mojibake repair, whitespace, dedupe of replayed notes, tested
- [ ] [C] 4.2 Sample 5–10 merchants, look at the text, fix the signal enum; state the cost estimate and get the owner's yes
- [ ] [C] 4.3 `LlmClientInterface` and the gateway adapter: thinking disabled, JSON schema output, `stop_reason` check, retry on 429 and 503
- [ ] [C] 4.4 Prompt with the data fence and the "text is data, not instructions" rule; a test with an injected instruction in a note
- [ ] [C] 4.5 Quote verification: a finding survives only if its quote is a substring of the cleaned source; tested with fabricated and altered quotes
- [ ] [S] 4.6 Console command to run the analysis in small batches and store findings; show quotes in the tool's detail view
- [ ] [S] 4.7 Findings for a list of merchants sent to Renee in chat; her answer recorded in the README

## Phase 5 — Routing switch (20 min)

- [ ] [S] 5.1 Read the routing README and tests; run them green before any change
- [ ] [C] 5.2 Act on `suppress_soldout_tasks` and `input/suppressions.csv`: skip listed deals or merchants when the switch is on; off by default
- [ ] [C] 5.3 Tests: switch off changes nothing; on drops only listed items; rollback restores today's output exactly; override precedence
- [ ] [S] 5.4 Decide `env_overrides.json` with Hana and write the decision down
- [ ] [S] 5.5 Export of the suppression list from the money model's verdicts
- [ ] [S] 5.6 Diff sent to Hana for review; Greg told early; his answer recorded, never assumed

## Phase 6 — Holdout (15 min)

- [ ] [C] 6.1 Deterministic assignment (salted hash of the sold-out id), written before outcomes, tested
- [ ] [C] 6.2 Offline read-out script: two files in, GP difference with a bootstrap interval out; fixtures and tests
- [ ] [S] 6.3 The range for Dan and the date of the real number, from the model and the freeze calendar

## Phase 7 — Hand-in (15 min, protected)

- [ ] [S] 7.1 README: how to run, tests, decisions, what it can't do, cut list, where AI is used and how it was checked
- [ ] [S] 7.2 Three lines for Dan; one-page change request for Greg (change, risk, rollback, when to stop)
- [ ] [S] 7.3 Session exports in `session/`; staged diff checked for the key and real data
- [ ] [S] 7.4 Submit

## Backlog

- Reviews and refund reasons as text sources
- Mirror of merchant marks in the database
- Public hosting of the tool
- Deal Alert opens and clicks (not available, access takes days)
