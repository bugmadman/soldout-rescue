# Session 002

Date: 2026-11-15, continued 2026-11-16 (CET). Process and planning. About 15 minutes of active time (owner's estimate).

Conventions: as in session 001. Assistant replies are given in full but condensed in wording.

---

## 1. Owner

Build the project on Symfony. About 2 hours 30 minutes are left. Everything the project needs is in `docs/`; first analyse it and build a development plan. Bring in the agent workflow (notes, plan, rules, agents), the DDD-for-a-solo-developer principles and the skeleton (Symfony, PostgreSQL, FrankenPHP, simpler Dockerisation), and reconcile them with this project.

**Assistant:** Read all documents in `docs/`, the routing code and the agent workflow and skeleton files of the reference setup. Wrote `AGENTS.md` (hard constraints and development principles), `WORKFLOW.md` (cycle, time boxing, commits), `CLAUDE.md` (two task tiers, LLM budget), `.claude/agents/developer.md` and `reviewer.md`, `NOTES.md` (decisions, cut order, rejected alternatives, inbox) and `PLAN.md` (seven phases with minute budgets, 145 of 150 minutes planned). Left out of the skeleton to save time: EasyAdmin, API Platform, Messenger, GrumPHP, Rector, PHPCS, translations. No code written and nothing committed.

Findings recorded in the notes:
- The routing service stays in Python and the holdout read-out is Python with the standard library, so both run offline without Docker; everything else is PHP.
- The peak freeze starts Monday and Greg's written OK is required, so Hana and Greg must be approached early, not at the deadline.
- The real holdout number cannot arrive before the freeze ends (7 January) plus 30 days; the range for Dan comes from the money model.
- Priya's £11 is not used as an amount: observational, auto-replenish ignored, effect concentrated in bands A and B.
- `.env` is currently ignored by git; phase 1 changes this so `.env` holds defaults and the key lives in `.env.local`.

Open questions put in the notes inbox: whether the holdout read-out reads only files; who warns Greg before the freeze; the £ rate per rep hour for the cost of rep time.

## 2. Owner

Paused the work.

**Assistant:** Acknowledged; nothing running, nothing committed.

## 3. Owner

Returned. Because a mobile version is needed, use Bootstrap loaded from a CDN to save time.

**Assistant:** Accepted. Recorded in `NOTES.md` (architecture) and in task 3.4: Bootstrap 5 from a CDN with a pinned exact version and SRI hashes, mobile-first, no Node and no asset build. Trade-off noted: the page needs internet to be styled; PHP tests do not load it.

## 4. Owner

Record in the README how much time this took (about 15 minutes by the owner's count), write the session history to `session/`, and give a commit title.

**Assistant:** Added a row to the README time table (15 minutes; total 43 of 180, remaining 137; start and end not recorded, so marked as the owner's estimate). Wrote this file. Proposed commit title below.

Proposed commit title: `Add project rules, agent workflow, notes and phased plan`
