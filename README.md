# Sold-out rescue

A mobile tool that shows reps today's sold-out deals with a call / don't-call recommendation and an amount in £. Money is computed in code, free text is read by an LLM with quotes, and a safe switch is added to the routing service. The effect is proven with a holdout.

Status: skeleton, historical estimates (2.1–2.3), the routing switch (5.1–5.3), the holdout assignment and the suppression list export (6.1, 6.4) are done. A reduced rep page runs on the CSV data pack (3.1, 3.3, 3.4): open `http://localhost:8080/` after `make upd`, with the data pack in `docs/data-pack/` (or `DATA_PACK_DIR` in `.env.local`). The text analysis, the full money model and the holdout read-out are not done. This file is a stub and will be filled in later (how to run, where AI is used).

## Cut list and limits

Cut for time, with the reason; each is a known gap, not a hidden one.

- **No functional test of the controller.** AGENTS.md asks for one with the service mocked; the page was checked by hand, and the service and the CSV adapter have unit tests.
- **No access-token check (3.6).** The page is open to anyone who can reach the address and shows real merchant names, cities and deal ids from the data pack. Run it only locally; do not expose the port.
- **No detail view (part of 3.4).** The page is a list only: no per-merchant evidence screen.
- **Refund risk is not in the formula.** The net value of a call is the historical GP difference per size band minus rep time (12 minutes at £20 an hour, £4). The refund-risk term from NOTES.md is not subtracted.
- **Estimates are per size band, not per merchant.** Every card of one band shows the same amount. The numbers are observational, not a causal effect; the holdout is meant to replace them.
- **No HTTP adapter for the sandbox (part of 3.1).** The tool reads the CSV data pack only.
- **Recommendations are two-valued (`call`, `don't call`).** `automation_covers` and `other` from 3.2 are not built, and no text signals feed the rules because phase 4 (LLM text analysis, quote verification) was not started.

Not done at this point, so not claimed anywhere: the holdout read-out script (6.2), the full `ChaseValue` range (2.4), the data-trap handling and fixed-window estimates (2.5, 2.7), the yearly number for Dan (2.8, 2.9), the mark form (3.5), the phone-width screenshots (3.7), the change request for Greg (5.7), and the diff review by Hana (5.4, 5.6). Real merchant names must not go into screenshots that are committed.

## Time spent

Budget: 3 hours of work. Active working time, timestamps in CET, updated at each check.

| Phase | Start (date, time) | End (date, time) | Breaks | Minutes |
|---|---|---|---|---|
| Setup: requirements saved to `docs/`, data pack and routing code reviewed, safety audit | 20:16 | 20:36 | – | 20 |
| Repository: routing copied in, git initialised, remote added | 20:36 | 20:44 | – | 8 |
| Process and plan: rules, workflow, agents, notes, phased plan, Bootstrap decision (time is the owner's estimate, start and end not recorded) | – | – | – | 15 |
| Plan audit: plan checked against the brief, carried-over wording removed, open questions listed, notes and plan updated (time is the owner's estimate, start and end not recorded) | – | – | – | 10 |
| Phase 1, skeleton: Docker, Makefile, Symfony 7.4 with FrankenPHP worker mode, PHPUnit, PHPStan, PHP-CS-Fixer, check from a clean clone (start is estimated from the last commit; the break is the owner's estimate) | 2026-11-16 13:15 | 2026-11-16 13:43 | 2 | 26 |
| Phase 2, money model so far: fixtures, `Money` value object, historical estimates, each with a review by a second agent (time is the owner's estimate of 30–35 minutes, 33 recorded; start and end not recorded; tasks 2.4–2.6 remain) | – | – | – | 33 |
| Phase 5, task 5.9: one message to Hana asking for a diff-review slot, drafted and sent by the owner (time is the owner's estimate, start and end not recorded) | – | – | – | 2 |
| Phase 5 and 6, tasks 5.1–5.3 (routing switch and tests) and 6.1, 6.4 (holdout assignment and list export), each with a review by a second agent (about 36 minutes elapsed, of which the owner was away for 10; the owner's estimate of 25 minutes is recorded; start and end not recorded) | – | – | 10 | 25 |
| Phase 3, tasks 3.1, 3.3, 3.4 (reduced): sandbox port with a CSV adapter, sold-out service with the net-value range, mobile page; each `[C]` task written by one agent and reviewed by another (the assistant's estimate of about 25 minutes; start and end not recorded) | – | – | – | 25 |
| **Total used** | | | | **164 of 180** |
| **Remaining** | | | | **16** |

Notes:
- Time is active working time. Each phase is the span between its start and end, and any break (a stopped session, waiting for material, a pause between days) is subtracted and noted in the Breaks column. Start and end carry the date.
- The log of the whole conversation is in [`session/`](session/).
- The table does not include the discussions with colleagues and the working out of who wants what and how (reading the answers of Priya, Hana, Greg, Dan and Renee and deciding what follows from them). The owner did not log that time while it happened, because it is not development time.

PS. A softer point about the time log: the time spent writing the instructions for the AI tools (rules, workflow, agents, notes, plan) is counted in the table above, 25 minutes in all. The owner would lean towards not counting it either, but it can be argued both ways. It is work, and instructions prepared over three hours would give better code than instructions written in twenty minutes, which would favour whoever spends more time on them. The table is left as it is, and a reader who disagrees can subtract the 25 minutes.

## Structure

```
.
├── README.md        this file
├── AGENTS.md        project rules
├── WORKFLOW.md      working method, time boxing, commits
├── NOTES.md         decisions, cuts, inbox
├── PLAN.md          phased task list with minute budgets
├── .claude/agents/  developer and reviewer agents
├── session/         conversation logs, one file per session
├── routing/         routing service, to get the sold-out switch
└── ...              rep tool, money code, text analysis, holdout script (to be added)
```

`docs/` holds the working brief and the data pack. It is not committed.

## Decisions and why

- **Holdout share 0.2.** The held-out deals are the control group: they keep their restock tasks, so the share is the price of the proof. A larger share would give a tighter comparison but would leave more rep time spent on deals the automation already covers; a smaller one would leave too few deals to see a difference. A dry run on the schedule data gave 306 held-out of 1,536 candidate deals, and a deal sells out about six times on average, so the control has many sold-outs. This is a judgment call, not a power calculation (no time for one). It is fixed once: the tool records the share next to the salt and refuses to run again with another share.
- **The export date is a parameter, not a constant.** The list is a snapshot of the schedules active on the date given with `--on`, built once before Friday's gate and unchanged until 7 January. The real date is the day the list is built; 11 November was used only for a dry run. Schedules created or ended later are not reflected until the freeze ends, which is why the date is stated with the list.
- **The list is keyed by `deal_id`.** One deal sells out about six times in the data (6,593 of 6,640 deals more than once), so a listed deal is suppressed for every future sold-out, which is what routing matches on.

## Review status

Time is very tight, so the code has not been fully reviewed by a human. The owner checked each step's result, not the code line by line. Every complex task was written by one agent and reviewed by another, and `make lint` (style, PHPStan strict, tests) passes, but that is not a human review. Treat the code as reviewed by tools and a second model only.

## Tech debt

Left on purpose because of the time box; the code works and is tested, but breaks two project rules:

- Task 2.3 passes bare `list<…>` between areas (`HistoricalRecords`, `HistoricalEstimates`) instead of `final readonly` collection classes.
- Layers are tangled in task 2.3: `HistoricalEstimator` (Domain) imports `DataPackDirectory` from Infrastructure, and Infrastructure imports Domain records. The reader interface should live in Infrastructure next to its implementation, with Domain depending only on the interface and DTOs.
- The historical estimates compare "called" and "left alone" by the first-call-before-restock rule, not over one fixed window; see `NOTES.md` for the bias.

## Known issues

- The Caddy admin endpoint stays enabled inside the `php` container (`localhost:2019`, not published). The FrankenPHP base image's healthcheck reads it, and `admin off` made the container report unhealthy, so `make init` failed on `--wait`. The proper fix is a custom `healthcheck` in `compose.yaml` that does not need the admin endpoint; it was left out for lack of time.
