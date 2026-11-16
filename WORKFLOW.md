# Workflow

How the work is run. The method relies on two files in the repository root, `NOTES.md` and `PLAN.md`, and stays the same when either of them is empty or missing. A missing file is created when it is first needed.

## NOTES.md and PLAN.md

- **NOTES.md** — a structured record of the current decisions, grouped by topic, plus "Rejected alternatives and cuts" (what was rejected or cut and why) and an "Inbox" for the owner's raw thoughts: ideas, doubts, fragments of requirements, written as they come.
- **PLAN.md** — a structured implementation plan with a task checklist and a minute budget per phase. The source of truth about what to do and in what order.
- **Task numbers** — every task in `PLAN.md` has a stable number `<phase>.<n>` (`3.2`), given once and never changed: a task inserted later takes the next free number in its phase. Commits and discussions refer to a task by its number.
- **Code review** — `[x]` means done and committed. The owner checks every step's result, but reviews the code only selectively: a task whose code the owner has reviewed gets ` — reviewed` at the end of its line.
- **Time log** — the time table in `README.md` is updated at the end of every phase (start, end, breaks, minutes). It is the single source for "how much is left".

## The cycle

1. The owner adds thoughts to the "Inbox" section of `NOTES.md`.
2. After discussion, each thought is moved from the inbox into the right section of `NOTES.md` (a superseded decision moves to "Rejected alternatives and cuts" with the reason), and the inbox is cleared. The plan is updated: tasks are added, changed or removed.
3. Tasks from `PLAN.md` are done one at a time and marked as done.
4. If new considerations come up during implementation, they go back into the `NOTES.md` inbox as one line, unverified.

Don't start implementing a task unless it is recorded in `PLAN.md` and the owner has explicitly asked to start it. Agreeing to a task or a decision is not that request: it is recorded in `NOTES.md`/`PLAN.md`, and those changes may be committed on their own. Never start a task while the owner is still checking the previous one: the working tree holds only the task under check. Don't turn `PLAN.md` into a dumping ground.

## Time boxing

- Each phase has a budget in minutes. At the end of a phase, record the actual minutes and the remaining total.
- At 80% of a phase budget, say so and name what will be cut if the phase does not finish. Cut by the cut order in `NOTES.md`, never by silently dropping a requirement.
- Every cut is written to the README cut list the moment it is decided, with the reason.
- The last 15 minutes are reserved for the README, the memos and the submission. They are not borrowed from.

## Task granularity

Each task in `PLAN.md` is a step the owner can verify in a few minutes. Don't combine several different actions into one task, but don't split below what is worth a separate check: the whole job has about 40 tasks, not 150.

## Scope and verification

- Spend effort in proportion to the task, and stay within it.
- Verify in proportion: one build or test run plus a check of the result itself. No exploratory experiments or research beyond what the task needs; a side consideration goes to the inbox.
- When the session's context has grown large, say so at a natural break (right after a commit), name the next task and suggest continuing in a new session: the decisions are already in `NOTES.md` and `PLAN.md`, so nothing is lost.
- Every session is exported to `session/` at the end of a phase (the hand-in asks for the AI sessions).

## Commits

- A step is committed only after the owner has seen the finished result and explicitly said to commit it. A yes to making changes is not a yes to committing.
- "Commit and continue" covers the step just shown, not the next one.
- A commit of a `PLAN.md` task ends with the trailer `Task: <number>`, so `git log --grep 'Task: 3.2'` finds the task's code. A commit outside the plan has none. History is kept: no squashing, no force-push.
- Before every commit, read `git diff --staged` for secrets (the sandbox key), real data files and anything from outside the project.
- Never bypass hooks (`--no-verify`) without asking.
- Chain whatever follows a commit with `&&`, so that a rejected commit stops it.
