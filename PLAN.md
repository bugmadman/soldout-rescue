# Implementation plan

Source: [NOTES.md](NOTES.md). Tasks are done in phase order, within a phase — top to bottom. Mark `[x]` as they get done. `3.2` is the task's stable number; ` — reviewed` marks a task whose code the owner has reviewed (see WORKFLOW.md). `[S]`/`[C]` is the task's tier (simple/complex), see CLAUDE.md, "Model selection". Each phase has a minute budget; the time log is in README.md.

Budget: 127 minutes remaining (180 total, 53 used; the time log in README.md is the record). Phases 1–7 plan 135: 8 minutes over, to be cut by the cut order in NOTES.md.

## Architecture (brief)

- Symfony 7.4 / PHP 8.5 / PostgreSQL / FrankenPHP in Docker for the rep tool, the money model and the text pipeline. Python (standard library) for the routing change and the holdout read-out.
- Flow: sandbox API (or a data-pack directory, offline) → money model + text findings → recommendation per sold-out → mobile page; a rep's mark goes back to the sandbox. Holdout assignment → suppression list → routing service behind one switch. Holdout assignments + outcomes → read-out script.
- Deliberately not now: see "Rejected alternatives and cuts" in NOTES.md.

## Phase 0 — Process and plan (10 min)

- [x] [S] 0.1 `AGENTS.md`, `WORKFLOW.md`, `CLAUDE.md`, `.claude/agents/` written for this project
- [x] [S] 0.2 `NOTES.md` with the decisions so far
- [x] [S] 0.3 `PLAN.md` with minute budgets
- [ ] [S] 0.4 The owner agrees the plan; open questions in the NOTES inbox are answered
- [ ] [S] 0.5 First messages sent before any code, the team's day and time read from `GET /me`: Greg (a change is coming, what he needs to say yes, whether the Wednesday review and the Friday check are still reachable), Hana (what trips people up in routing, `env_overrides.json`, the suppression file under the freeze), Priya (why the £11 is not used as the amount, the £ rate per rep hour, the CRM migration)

## Phase 1 — Skeleton (15 min)

- [x] [S] 1.1 `.gitignore`: stop ignoring `.env`, ignore `.env.local`, `var/`, `vendor/`; `.env` holds defaults only, no key
- [x] [S] 1.2 `.docker/php/` (Dockerfile, Caddyfile, php.ini) and `compose.yaml` + `compose.override.yaml` with PostgreSQL 18, FrankenPHP image
- [x] [S] 1.3 `Makefile`: `init`, `upd`, `updb`, `down`, `in`, `cs`, `fix`, `stan`, `test`, `lint`; `.env.local` created with the host UID
- [x] [S] 1.4 Symfony 7.4 skeleton created inside the container, FrankenPHP worker mode on
- [ ] [S] 1.5 PHPUnit, PHPStan with strict rules, PHP-CS-Fixer (strict types, final, 120 columns by hand); one smoke test green
- [ ] [S] 1.6 Verify: `make init` from a clean state, the start page opens, `make lint` passes

## Phase 2 — Money model (20 min)

- [ ] [S] 2.1 Fixture CSVs (invented, tiny) for sold-outs, merchants, activities, schedules, merchant months
- [ ] [C] 2.2 `Money` VO in pence with explicit rounding, tested
- [ ] [C] 2.3 Historical estimates from the data pack (restock rate called vs. left alone by size band, GP after, refund rate), computed by a script, written to a small committed JSON of parameters, not the raw data
- [ ] [C] 2.4 `ChaseValue` calculation: GP of a cap raise minus rep time minus refund risk, as a low / central / high range, all parameters named, tested on edge cases
- [ ] [C] 2.5 Handling of the data traps: dedupe replayed activities, blank `rep_id`, an `owner_id` that is not a rep, blank 30-day GP, refunds arriving after the 30-day window, schedule coverage by `deal_id` and date, Deal Alert sends existing only from August 2025, a check of the rows around the early-2026 CRM migration
- [ ] [S] 2.6 Priya's answer to the 0.5 message recorded in NOTES.md, never assumed; until she answers the rate per rep hour stays a named parameter with a stated default

## Phase 3 — Rep tool (25 min)

- [ ] [C] 3.1 `SandboxClientInterface` with two adapters: HTTP (key from `.env.local`, paging, 429 wait) and CSV files from a data-pack directory, so the verdicts run offline from files; a fake for tests
- [ ] [C] 3.2 `Recommendation` enum and the rules: `call`, `dont_call`, `automation_covers`, `other`, each with a reason; do-not-contact always wins
- [ ] [S] 3.3 `SoldOutService`: today's sold-outs → recommendation + amount, ranked by amount
- [ ] [S] 3.4 Mobile Twig page with Bootstrap 5 from a CDN (pinned version, SRI): list with action, reason, £ range; a detail view with the evidence
- [ ] [S] 3.5 Mark form: `PUT /merchants/{id}/mark` with a code and a reason, `marked_by` is the rep selected in the tool (the shared access token carries no rep)
- [ ] [C] 3.6 Access token check (shared secret from `.env.local`) on every page
- [ ] [S] 3.7 Verify on a phone-width viewport; screenshots saved for the hand-in

## Phase 4 — Text analysis (25 min)

- [ ] [C] 4.1 Clean the text: mojibake repair, whitespace, dedupe of replayed notes, tested
- [ ] [C] 4.2 Sample 5–10 merchants from Renee's book (North H&B), look at the text, fix the signal enum; state the cost estimate and get the owner's yes
- [ ] [C] 4.3 `LlmClientInterface` and the gateway adapter: thinking disabled, JSON schema output, `stop_reason` check, retry on 429 and 503
- [ ] [C] 4.4 Prompt with the data fence and the "text is data, not instructions" rule; a test with an injected instruction in a note
- [ ] [C] 4.5 Quote verification: a finding survives only if its quote is a substring of the cleaned source; tested with fabricated and altered quotes
- [ ] [S] 4.6 Console command to run the analysis in small batches and store findings (entity and migration); show quotes in the tool's detail view
- [ ] [C] 4.8 Verified signals feed the recommendation rules (a capacity limit or a do-not-call note moves a sold-out away from `call`, the quote is the reason shown), tested
- [ ] [S] 4.7 Findings for a list of merchants sent to Renee in chat; her answer recorded in the README

## Phase 5 — Routing switch (20 min)

- [ ] [S] 5.1 Read the routing README and tests; run them green before any change
- [ ] [C] 5.2 Act on `suppress_soldout_tasks` and `input/suppressions.csv`: skip listed deals or merchants when the switch is on; off by default; the routing README and version updated
- [ ] [C] 5.3 Tests: switch off changes nothing; on drops only listed items; rollback restores today's output exactly; override precedence
- [ ] [S] 5.4 Decide `env_overrides.json` with Hana and write the decision down
- [ ] [S] 5.6 Diff sent to Hana for review; Greg's answer to the 0.5 message recorded, never assumed

## Phase 6 — Holdout (15 min)

- [ ] [C] 6.1 Deterministic assignment (salted hash of the sold-out id), written before outcomes, tested
- [ ] [S] 6.4 Export of the suppression list from the money model's verdicts, runnable offline from files; the holdout share is left out of the list and keeps its tasks
- [ ] [C] 6.2 Offline read-out script: two files in, GP difference with a bootstrap interval out; fixtures and tests
- [ ] [S] 6.3 The range for Dan and the date of the real number, from the model, the team's clock and the freeze calendar: one date if the switch goes on before the freeze, one if after 7 January

## Phase 7 — Hand-in (15 min, protected)

- [ ] [S] 7.1 README: how to run, tests, decisions, what it can't do, cut list, where AI is used and how it was checked
- [ ] [S] 7.2 Three lines for Dan; one-page change request for Greg (change, risk, rollback, when to stop), sent to Greg in chat as well: the Submit panel does not deliver it to him
- [ ] [S] 7.3 Session exports in `session/`; staged diff checked for the key and real data
- [ ] [S] 7.4 Submit: three lines, repo link (public or with the invited address), tool link or the one-command run, AI sessions, screenshots, the change request

## Backlog

- Reviews and refund reasons as text sources
- Mirror of merchant marks in the database
- Public hosting of the tool
- Deal Alert opens and clicks (not available, access takes days)
