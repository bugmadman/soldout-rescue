# Implementation plan

Source: [NOTES.md](NOTES.md). Tasks are done in phase order, within a phase — top to bottom, unless a phase heading or "Next steps" says otherwise. Mark `[x]` as they get done. `3.2` is the task's stable number (gaps in the numbering are tasks that were never made); ` — reviewed` marks a task whose code the owner has reviewed (see WORKFLOW.md). `[S]`/`[C]` is the task's tier (simple/complex), see CLAUDE.md, "Model selection". Each phase has a minute budget; the time log is in README.md.

Budget: 8 minutes remained at the last time-log entry (180 total, 172 used; the time log in README.md is the record, update it before planning). Phase 1 took 26 of 15 minutes; phase 2 so far 33 of 20 (tasks 2.1–2.3 done). Phases 2–7 plan far more than the remaining time, and the tasks added since (2.7–2.9, 5.7–5.9, the rewritten 6.1 and 6.4) add more: what is cut is decided first thing in the next session, by the cut order in NOTES.md.

## Next steps

A proposed order for the next session. Nothing starts until the owner asks for it (WORKFLOW.md). Deadlines: Greg's page by Thursday morning (for Friday's gate), Dan's Thursday 09:00 and the number for Friday's deck; the freeze starts Monday.

1. Read the time log in README.md, decide what is cut (NOTES.md, open questions).
2. 5.9: ask Hana for a diff-review slot (one message); 0.6: Renee's two questions can go at the same time.
3. 5.1–5.3: the routing switch with tests.
4. 2.5 (at least the dedupe of activities), then 2.7 (fixed window, schedule coverage), then 2.9 (the schedule share and method to Priya), then 2.8 (the yearly number to Dan).
5. 6.1 and 6.4: the deal-level holdout and the static suppression list.
6. 5.4 and 5.6: `env_overrides.json` question and the diff to Hana.
7. 5.7: the one-page change request to Greg, by Thursday morning at the latest.
8. Then 2.4, phases 3 and 4, 6.2, 6.3 and phase 7, as far as the budget allows.

## Architecture (brief)

- Symfony 7.4 / PHP 8.5 / PostgreSQL / FrankenPHP in Docker for the rep tool, the money model and the text pipeline. Python (standard library) for the routing change and the holdout read-out.
- Flow: sandbox API (or a data-pack directory, offline) → money model + text findings → recommendation per sold-out → mobile page; a rep's mark goes back to the sandbox. Holdout assignment (by deal) → static suppression list → routing service behind one switch. Holdout assignments + outcomes → read-out script.
- Deliberately not now: see "Rejected alternatives and cuts" in NOTES.md.

## Phase 0 — Process and plan (10 min)

- [x] [S] 0.1 `AGENTS.md`, `WORKFLOW.md`, `CLAUDE.md`, `.claude/agents/` written for this project
- [x] [S] 0.2 `NOTES.md` with the decisions so far
- [x] [S] 0.3 `PLAN.md` with minute budgets
- [ ] [S] 0.4 The owner agrees the plan; the open questions in NOTES.md are answered or cut
- [x] [S] 0.5 First messages sent before any code to Greg, Hana and Priya, all answered (recorded in NOTES.md, "Colleague answers")
- [x] [S] 0.6 Renee, two questions, sent at about 12:30 in reply to her own message asking to see the tool: how many days after a sell-out she usually calls, and which merchants she skips on purpose because they raise the cap themselves. Her answers are a check of N, not an input, and are recorded in NOTES.md when they come. She has not answered yet
- [x] [S] 0.7 Three-line update to Dan, sent at 11:41 on the team's clock:
  1. Greg: I'm sending him the change in one sentence today and the one-page request by Thursday morning.
  2. Your yearly number: a range with the assumptions, not one figure; Priya's £11 x the whole queue doesn't hold up.
  3. I need one figure from you: £ per rep hour for a rep's time (the data only has 12 min a task, no cost). Rough is fine; if I don't hear, I'll use £20 and say so.
- [x] [S] 0.8 Follow-up to Dan, sent after his 12:00 answer: Greg has the change in one sentence and counts it as discussed for Friday's gate; the rate is ours, £20 tested at £15-£30; the £11 is sound data with an extrapolation that does not hold, shown in numbers

## Phase 1 — Skeleton (15 min)

- [x] [S] 1.1 `.gitignore`: stop ignoring `.env`, ignore `.env.local`, `var/`, `vendor/`; `.env` holds defaults only, no key
- [x] [S] 1.2 `.docker/php/` (Dockerfile, Caddyfile, php.ini) and `compose.yaml` + `compose.override.yaml` with PostgreSQL 18, FrankenPHP image
- [x] [S] 1.3 `Makefile`: `init`, `upd`, `updb`, `down`, `in`, `cs`, `fix`, `stan`, `test`, `lint`; `.env.local` created with the host UID
- [x] [S] 1.4 Symfony 7.4 skeleton created inside the container, FrankenPHP worker mode on
- [x] [S] 1.5 PHPUnit, PHPStan with strict rules, PHP-CS-Fixer (strict types, final, 120 columns by hand); one smoke test green
- [x] [S] 1.6 Verify: `make init` from a clean state, the start page opens, `make lint` passes

## Phase 5 — Routing switch (20 min, moved ahead of the rest of phase 2)

Moved forward so that the change can reach Greg: Friday's gate takes only what he has already discussed (he does, since 11:34) and wants the page by Thursday morning; today's 15:00 review is reachable only with a diff that Hana has reviewed. The next window after that is after the freeze ends on 7 January. Order of work is in "Next steps": 5.9, 5.1–5.3, then 6.1 and 6.4, then 5.4 and 5.6, then 5.7.

- [x] [S] 5.1 Read the routing README and tests; run them green before any change
- [x] [C] 5.2 Act on `suppress_soldout_tasks` and `input/suppressions.csv`: skip listed deals when the switch is on; off by default; the routing README and version updated
- [x] [C] 5.3 Tests: switch off changes nothing; on drops only listed items; rollback restores today's output exactly; override precedence
- [ ] [S] 5.4 Decide `env_overrides.json` with Hana (she has not answered it, ask it with the 5.6 message) and write the decision down
- [ ] [S] 5.6 Diff sent to Hana for review once a slot is agreed (5.9); she reads code and offers a 15-minute pairing. Greg's written OK is recorded when it comes, never assumed
- [ ] [S] 5.7 One-page change request for Greg, by Thursday morning for Friday's gate (and before today's 15:00 review if a Hana-reviewed diff exists), not claiming that Hana has seen it before she has: the change, risk, rollback, when to stop. It says: the suppression file is keyed by `deal_id` and is config under the freeze, and the list is static until 7 January (a change is a signed emergency); the two halves, deals covered by automation (accepted in principle) and "call isn't wanted" (he judges on the page; automation half only if the text analysis is not ready), with a `reason` per row so the second half can be struck; whether the switch goes on before Monday or stays off until 7 January. Sent to Greg in chat as well, the Submit panel does not deliver it to him
- [x] [S] 5.8 Greg's question answered: the change in one sentence sent to him at 11:34; he counts it as discussed for Friday and asked for the page by Thursday morning
- [x] [S] 5.9 Ask Hana for a slot to review the diff before Thursday morning (Greg's condition; her Wednesday window until 10:30 has passed); never claim she has seen it before she has

## Phase 2 — Money model (20 min)

Order within the phase: 2.5, 2.7, 2.9, 2.8, then 2.4.

- [x] [S] 2.1 Fixture CSVs (invented, tiny) for sold-outs, merchants, activities, schedules, merchant months
- [x] [C] 2.2 `Money` VO in pence with explicit rounding, tested
- [x] [C] 2.3 Historical estimates from the data pack (restock rate called vs. left alone by size band, GP after, refund rate), computed by a script, written to a small committed JSON of parameters, not the raw data
- [ ] [C] 2.5 Handling of the data traps: dedupe replayed activities (526 candidate rows repeating on all fields but the id; the file has dates only, so the key is the date, not the time; check by activity type before dropping), blank `rep_id`, a non-rep `owner_id` (none in the CSV pack; check a small sample of merchants through `GET /activities?merchant_id=`, within the rate limit), blank 30-day GP, refunds arriving after the 30-day window, schedule coverage by `deal_id` and date, Deal Alert sends existing only from August 2025, a check of the rows around the early-2026 CRM migration
- [ ] [C] 2.7 Redo the historical estimates with a fixed window: "called" is a call within N days of the sold-out date, N from the data (median or 75th percentile of days to the first deduplicated call), a sensitivity table for N = 3, 7, 14, split by schedule coverage; `config/historical_estimates.json` rewritten; run before 2.4 uses the numbers
- [ ] [S] 2.9 Send Priya, in chat, the share of left-alone sold-outs covered by a schedule together with the method (how schedules are joined, by `deal_id` and date, and how coverage is counted). Promised: it goes to her before it goes into anything for Dan, and her answer is not waited for. She offered to sanity-check the join: at her desk until 12:30 and around in the afternoon except about 15:30
- [ ] [S] 2.8 The yearly number for Dan's Friday deck: one central figure with the range next to it, labelled a model estimate that the holdout replaces; named assumptions (volume, per-call GP by band, rep time at £20, tested at £15-£30), from the 2.7 estimates; a short numeric answer to "why the £11 does not hold" (reps choose whom to call, schedules top up part of "left alone", the gain is in the large merchants, £340k is an average times the whole queue) that keeps Priya's data as sound; only after 2.9; sent to Dan in three lines
- [ ] [C] 2.4 `ChaseValue` calculation: GP of a cap raise minus rep time minus refund risk, as a low / central / high range, all parameters named, tested on edge cases
- [x] [S] 2.6 Priya's answer to the 0.5 message recorded in NOTES.md, never assumed (she has no rate; Dan said to use ours, £20)

## Phase 3 — Rep tool (25 min)

- [x] [C] 3.1 `SandboxClientInterface` with the CSV adapter (files from a data-pack directory, so the verdicts run offline) and a fake for tests; the HTTP adapter (key from `.env.local`, paging, 429 wait) is cut for time
- [ ] [C] 3.2 `Recommendation` enum and the rules: `call`, `dont_call`, `automation_covers`, `other`, each with a reason; do-not-contact always wins
- [x] [S] 3.3 `SoldOutService`: today's sold-outs → recommendation + amount, ranked by amount (done as `[C]`, with the two-valued recommendation, net value per size band, do-not-contact first)
- [x] [S] 3.4 Mobile Twig page with Bootstrap 5 from a CDN (pinned version, SRI): list with action, reason, £ range; a detail view with the evidence (the list is done, the detail view is cut)
- [ ] [S] 3.5 Mark form: `PUT /merchants/{id}/mark` with a code and a reason, `marked_by` is the rep selected in the tool (the shared access token carries no rep)
- [x] [C] 3.6 HTTP Basic auth on every route, login and password from `.env.local` (done instead of the shared access token; a request subscriber, fail-closed when the variables are empty)
- [ ] [S] 3.7 Verify on a phone-width viewport; screenshots saved for the hand-in

## Phase 4 — Text analysis (25 min)

- [ ] [C] 4.1 Clean the text: mojibake repair, whitespace, dedupe of replayed notes (380 notes repeat on merchant, rep, time and text with a new id; Hana confirms the replay), tested
- [ ] [C] 4.2 Sample 5–10 merchants from Renee's book (North H&B), look at the text, fix the signal enum; state the cost estimate and get the owner's yes
- [ ] [C] 4.3 `LlmClientInterface` and the gateway adapter: thinking disabled, JSON schema output, `stop_reason` check, retry on 429 and 503
- [ ] [C] 4.4 Prompt with the data fence and the "text is data, not instructions" rule; a test with an injected instruction in a note
- [ ] [C] 4.5 Quote verification: a finding survives only if its quote is a substring of the cleaned source; tested with fabricated and altered quotes
- [ ] [S] 4.6 Console command to run the analysis in small batches and store findings (entity and migration); show quotes in the tool's detail view
- [ ] [S] 4.7 Findings for a list of merchants sent to Renee in chat; her answer recorded in the README, with the note that she covers one book only. She was asked in 0.6 whether she would check a few findings but has not agreed yet; her answers to the two questions of 0.6 are recorded in NOTES.md
- [ ] [C] 4.8 Verified signals feed the recommendation rules (a capacity limit or a do-not-call note moves a sold-out away from `call`, the quote is the reason shown), tested

## Phase 6 — Holdout (15 min)

6.1 and 6.4 come right after 5.1–5.3: the list that passes Friday's gate is the list until 7 January (Greg).

- [x] [C] 6.1 Deterministic assignment (salted hash of the `deal_id`) at the deal level, done once before the list is built and written before outcomes, tested
- [x] [S] 6.4 Export of the static suppression list for Friday's gate: deals with an active auto-replenish schedule (a snapshot on the export date) and, as separate rows by `reason`, deals where a call is not wanted (only if the text analysis is ready, otherwise the automation half alone); keyed by `deal_id`, mapped from the sold-out id; the held-out deals are left out of the list and keep their tasks; runnable offline from files; first check how often one deal sells out
- [ ] [C] 6.2 Offline read-out script: two files in, GP difference with a bootstrap interval out; fixtures and tests
- [ ] [S] 6.3 The range for Dan and the date of the real number, from the model, the team's clock and the freeze calendar: one date if the switch goes on before the freeze, one if after 7 January

## Phase 7 — Hand-in (15 min, protected)

- [ ] [S] 7.1 README: how to run, tests, decisions, what it can't do, cut list, where AI is used and how it was checked; the rate of £20 as a named parameter
- [ ] [S] 7.2 Three lines for Dan; the change request (5.7) brought up to date with the final numbers and the review outcome
- [ ] [S] 7.3 Session exports in `session/`; staged diff checked for the key and real data
- [ ] [S] 7.4 Submit: three lines, repo link (public or with the invited address), tool link or the one-command run, AI sessions, screenshots, the change request

## Backlog

- Reviews and refund reasons as text sources
- Mirror of merchant marks in the database
- Public hosting of the tool
- Deal Alert opens and clicks (not available, access takes days)
