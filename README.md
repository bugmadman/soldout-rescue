# Sold-out rescue

A mobile tool that shows reps today's sold-out deals with a call / don't-call recommendation and an amount in £. Money is computed in code, free text is read by an LLM with quotes, and a safe switch is added to the routing service. The effect is proven with a holdout.

Status: setup only. Nothing is built yet. This file is a stub and will be filled in later (how to run, what was decided, what it can't do, what was cut and why, where AI is used).

## Time spent

Budget: 3 hours of work. Active working time, timestamps in CET, updated at each check.

| Phase | Start (date, time) | End (date, time) | Breaks | Minutes |
|---|---|---|---|---|
| Setup: requirements saved to `docs/`, data pack and routing code reviewed, safety audit | 20:16 | 20:36 | – | 20 |
| Repository: routing copied in, git initialised, remote added | 20:36 | 20:44 | – | 8 |
| Process and plan: rules, workflow, agents, notes, phased plan, Bootstrap decision (time is the owner's estimate, start and end not recorded) | – | – | – | 15 |
| Plan audit: plan checked against the brief, carried-over wording removed, open questions listed, notes and plan updated (time is the owner's estimate, start and end not recorded) | – | – | – | 10 |
| Phase 1, skeleton: Docker, Makefile, Symfony 7.4 with FrankenPHP worker mode, PHPUnit, PHPStan, PHP-CS-Fixer, check from a clean clone (start is estimated from the last commit; the break is the owner's estimate) | 2026-11-16 13:15 | 2026-11-16 13:43 | 2 | 26 |
| **Total used** | | | | **79 of 180** |
| **Remaining** | | | | **101** |

Notes:
- Time is active working time. Each phase is the span between its start and end, and any break (a stopped session, waiting for material, a pause between days) is subtracted and noted in the Breaks column. Start and end carry the date.
- The log of the whole conversation is in [`session/`](session/).

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

## Known issues

- The Caddy admin endpoint stays enabled inside the `php` container (`localhost:2019`, not published). The FrankenPHP base image's healthcheck reads it, and `admin off` made the container report unhealthy, so `make init` failed on `--wait`. The proper fix is a custom `healthcheck` in `compose.yaml` that does not need the admin endpoint; it was left out for lack of time.
