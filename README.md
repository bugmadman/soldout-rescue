# Sold-out rescue

A mobile tool that shows reps today's sold-out deals with a call / don't-call recommendation and an amount in £. Money is computed in code, free text is read by an LLM with quotes, and a safe switch is added to the routing service. The effect is proven with a holdout.

Status: skeleton done, money model in progress (tasks 2.1–2.3 written). This file is a stub and will be filled in later (how to run, what was decided, what it can't do, what was cut and why, where AI is used).

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
| **Total used** | | | | **114 of 180** |
| **Remaining** | | | | **66** |

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
