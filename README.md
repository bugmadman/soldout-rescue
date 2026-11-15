# Sold-out rescue

A mobile tool that shows reps today's sold-out deals with a call / don't-call recommendation and an amount in £. Money is computed in code, free text is read by an LLM with quotes, and a safe switch is added to the routing service. The effect is proven with a holdout.

Status: setup only. Nothing is built yet. This file is a stub and will be filled in later (how to run, what was decided, what it can't do, what was cut and why, where AI is used).

## Time spent

Budget: 3 hours of work. Clock time, one timezone (CET), updated at each check.

| Phase | Start | End | Minutes |
|---|---|---|---|
| Setup: requirements saved to `docs/`, data pack and routing code reviewed, safety audit | 20:16 | 20:36 | 20 |
| Repository: routing copied in, git initialised, remote added | 20:36 | 20:44 | 8 |
| **Total used** | | | **28 of 180** |
| **Remaining** | | | **152** |

Notes:
- Time is wall clock from the first message, so it includes waiting for pasted material.
- The log of the whole conversation is in [`session/`](session/).

## Structure

```
.
├── README.md        this file
├── session/         conversation logs, one file per session
├── routing/         routing service, to get the sold-out switch
└── ...              rep tool, money code, text analysis, holdout script (to be added)
```

`docs/` holds the working brief and the data pack. It is not committed.
